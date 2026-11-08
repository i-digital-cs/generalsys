package net.genral.generalhouse;

import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.os.Environment;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.webkit.CookieManager;
import android.webkit.URLUtil;
import android.webkit.WebView;
import android.widget.Toast;

import androidx.activity.OnBackPressedCallback;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;

import com.getcapacitor.BridgeActivity;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.nio.charset.StandardCharsets;

/**
 * GNRL Operations Capacitor host.
 *
 * System-bar strategy (ONE path only):
 * - Edge-to-edge drawing (decorFits=false).
 * - Apply WindowInsetsCompat padding to the Cap bridge root (WebView parent).
 * - Parent background = brand status color so the top inset strip matches the header.
 * - Do NOT also pad via CSS safe-area (would double-offset).
 *
 * Why not setDecorFitsSystemWindows(true) alone:
 * Cap BridgeActivity swaps to capacitor AppTheme.NoActionBar, SplashScreen/Activity
 * EdgeToEdge may keep content under system bars, and the WebView never receives
 * reserved space unless insets are applied to its container.
 */
public class MainActivity extends BridgeActivity {
    private static final int STATUS_BAR_COLOR = Color.parseColor("#79CAF9");

    private AppUpdateChecker updateChecker;
    private View insetTarget;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        assertConfiguredHost();
        applyWebViewSystemInsets();

        updateChecker = new AppUpdateChecker(this);
        updateChecker.checkOnLaunch();

        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                // Mandatory update Dialog must not be bypassed via BACK / WebView history.
                if (updateChecker != null && updateChecker.consumeBackPress()) {
                    return;
                }
                WebView webView = getBridge() != null ? getBridge().getWebView() : null;
                if (webView != null && webView.canGoBack()) {
                    webView.goBack();
                    return;
                }
                setEnabled(false);
                getOnBackPressedDispatcher().onBackPressed();
                setEnabled(true);
            }
        });

        WebView webView = getBridge() != null ? getBridge().getWebView() : null;
        if (webView == null) {
            return;
        }

        webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, contentLength) -> {
            try {
                DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
                request.setMimeType(mimeType);
                String cookies = CookieManager.getInstance().getCookie(url);
                if (cookies != null) {
                    request.addRequestHeader("cookie", cookies);
                }
                request.addRequestHeader("User-Agent", userAgent);
                request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                String filename = URLUtil.guessFileName(url, contentDisposition, mimeType);
                request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, filename);
                DownloadManager dm = (DownloadManager) getSystemService(DOWNLOAD_SERVICE);
                if (dm != null) {
                    dm.enqueue(request);
                    Toast.makeText(this, "Downloading " + filename, Toast.LENGTH_SHORT).show();
                }
            } catch (Exception e) {
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
                } catch (ActivityNotFoundException ignored) {
                    Toast.makeText(this, "Unable to download file", Toast.LENGTH_SHORT).show();
                }
            }
        });
    }

    @Override
    public void onResume() {
        super.onResume();
        // Splash / EdgeToEdge can reassert after resume — re-request insets only (same strategy).
        if (insetTarget != null) {
            WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
            ViewCompat.requestApplyInsets(insetTarget);
        }
        if (updateChecker != null) {
            updateChecker.checkOnResume();
        }
    }

    /**
     * Single inset strategy: pad Cap bridge root with systemBars + displayCutout.
     */
    private void applyWebViewSystemInsets() {
        Window window = getWindow();
        WindowCompat.setDecorFitsSystemWindows(window, false);
        window.setStatusBarColor(Color.TRANSPARENT);
        window.setNavigationBarColor(Color.TRANSPARENT);

        View decor = window.getDecorView();
        WindowInsetsControllerCompat controller = WindowCompat.getInsetsController(window, decor);
        if (controller != null) {
            controller.setAppearanceLightStatusBars(true);
            controller.setAppearanceLightNavigationBars(true);
        }

        WebView webView = getBridge() != null ? getBridge().getWebView() : null;
        if (webView == null) {
            return;
        }

        View target = webView.getParent() instanceof View ? (View) webView.getParent() : webView;
        insetTarget = target;

        // Inset strips (top/bottom/sides) show this color behind system icons.
        target.setBackgroundColor(STATUS_BAR_COLOR);

        ViewCompat.setOnApplyWindowInsetsListener(target, (v, windowInsets) -> {
            Insets bars = windowInsets.getInsets(
                WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.displayCutout()
            );
            v.setPadding(bars.left, bars.top, bars.right, bars.bottom);

            // WebView must not add a second inset pass.
            if (v instanceof ViewGroup) {
                WebView childWeb = findWebView((ViewGroup) v);
                if (childWeb != null) {
                    childWeb.setPadding(0, 0, 0, 0);
                    childWeb.setBackgroundColor(Color.TRANSPARENT);
                }
            }

            return WindowInsetsCompat.CONSUMED;
        });
        ViewCompat.requestApplyInsets(target);
    }

    private static WebView findWebView(ViewGroup group) {
        for (int i = 0; i < group.getChildCount(); i++) {
            View child = group.getChildAt(i);
            if (child instanceof WebView) {
                return (WebView) child;
            }
            if (child instanceof ViewGroup) {
                WebView nested = findWebView((ViewGroup) child);
                if (nested != null) {
                    return nested;
                }
            }
        }
        return null;
    }

    /** Exposed for AppUpdateChecker to disable WebView interaction during mandatory updates. */
    WebView capacitorWebView() {
        return getBridge() != null ? getBridge().getWebView() : null;
    }

    private void assertConfiguredHost() {
        String expectedHost = BuildConfig.GH_SERVER_HOST;
        String env = BuildConfig.GH_ENV;
        try (InputStream in = getAssets().open("capacitor.config.json");
             BufferedReader reader = new BufferedReader(new InputStreamReader(in, StandardCharsets.UTF_8))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line);
            }
            JSONObject root = new JSONObject(sb.toString());
            String url = root.optJSONObject("server") != null
                ? root.getJSONObject("server").optString("url", "")
                : "";
            Uri uri = Uri.parse(url);
            String host = uri.getHost() == null ? "" : uri.getHost();
            if (!expectedHost.equalsIgnoreCase(host)) {
                Toast.makeText(
                    this,
                    "Misconfigured " + env + " build: expected " + expectedHost + " but config has " + host,
                    Toast.LENGTH_LONG
                ).show();
                finish();
                return;
            }
            if ("LIVE".equals(env) && host.toLowerCase().contains("generalsys.idigital-cs.com")) {
                Toast.makeText(this, "LIVE build must not use TEST host", Toast.LENGTH_LONG).show();
                finish();
            }
        } catch (Exception e) {
            Toast.makeText(this, "Missing Capacitor server configuration", Toast.LENGTH_LONG).show();
            finish();
        }
    }
}
