package net.genral.generalhouse;

import android.app.Activity;
import android.app.Dialog;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.net.Uri;
import android.os.Handler;
import android.os.Looper;
import android.view.KeyEvent;
import android.view.LayoutInflater;
import android.view.View;
import android.view.Window;
import android.view.WindowManager;
import android.webkit.WebView;
import android.widget.Button;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * Native GNRL Operations update enforcement.
 *
 * Mandatory updates use a non-cancelable full-screen Dialog at the Android shell layer.
 * The Cap WebView is disabled while the blocker is visible. Back / outside taps cannot dismiss it.
 * Optional updates remain dismissible. Network failures soft-fail and never clear an active mandatory block.
 */
final class AppUpdateChecker {
    private static final String PREFS = "gh_app_update";
    private static final String KEY_LAST_CHECK_MS = "last_check_ms";
    private static final String KEY_OPTIONAL_DISMISSED_BUILD = "optional_dismissed_build";
    private static final long THROTTLE_MS = 15L * 60L * 1000L;

    private final Activity activity;
    private final Handler main = new Handler(Looper.getMainLooper());
    private final ExecutorService io = Executors.newSingleThreadExecutor();
    private final AtomicBoolean checkInFlight = new AtomicBoolean(false);

    private Dialog dialog;
    private View dialogContent;
    private boolean mandatoryVisible = false;
    private String pendingUpdateUrl = "";

    AppUpdateChecker(Activity activity) {
        this.activity = activity;
    }

    void checkOnLaunch() {
        check(true);
    }

    void checkOnResume() {
        // Always re-evaluate when a mandatory block is up (after store/installer return).
        check(mandatoryVisible);
    }

    /** True while a mandatory update Dialog is showing — MainActivity must consume BACK. */
    boolean isBlocking() {
        return mandatoryVisible && dialog != null && dialog.isShowing();
    }

    /**
     * @return true if the back event was consumed (mandatory block active).
     */
    boolean consumeBackPress() {
        return isBlocking();
    }

    private void check(boolean force) {
        SharedPreferences prefs = activity.getSharedPreferences(PREFS, Activity.MODE_PRIVATE);
        long now = System.currentTimeMillis();
        long last = prefs.getLong(KEY_LAST_CHECK_MS, 0L);
        if (!force && !mandatoryVisible && (now - last) < THROTTLE_MS) {
            return;
        }
        if (!checkInFlight.compareAndSet(false, true)) {
            return;
        }

        io.execute(() -> {
            try {
                String serverBase = readServerBase();
                int build = installedVersionCode();
                String versionName = installedVersionName();
                String channel = "LIVE".equals(BuildConfig.GH_ENV) ? "live" : "test";

                String q = "platform=android"
                    + "&build_number=" + build
                    + "&version_name=" + URLEncoder.encode(versionName, StandardCharsets.UTF_8.name())
                    + "&app_channel=" + channel;

                URL url = new URL(serverBase + "/app/version-check?" + q);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setConnectTimeout(8000);
                conn.setReadTimeout(8000);
                conn.setRequestMethod("GET");
                conn.setRequestProperty("Accept", "application/json");

                int code = conn.getResponseCode();
                InputStream stream = code >= 200 && code < 300 ? conn.getInputStream() : conn.getErrorStream();
                String body = readAll(stream);
                conn.disconnect();

                // Record attempt time only for successful transport — failed checks retry sooner.
                if (code >= 200 && code < 300) {
                    prefs.edit().putLong(KEY_LAST_CHECK_MS, System.currentTimeMillis()).apply();
                }

                if (code < 200 || code >= 300) {
                    // Soft-fail: keep any existing mandatory dialog; do not unlock.
                    return;
                }

                JSONObject json = new JSONObject(body);
                int dismissed = prefs.getInt(KEY_OPTIONAL_DISMISSED_BUILD, 0);
                AppUpdateDecision decision = AppUpdateDecision.evaluate(
                    json.optBoolean("service_ok", false),
                    json.optBoolean("update_required", false),
                    json.optBoolean("update_available", false),
                    json.optInt("latest_build", 0),
                    json.optString("latest_version", ""),
                    json.optString("update_url", ""),
                    json.optString("release_notes", ""),
                    dismissed
                );

                main.post(() -> applyDecision(decision));
            } catch (Exception ignored) {
                // Soft-fail on network / parse errors — never clear a mandatory block.
            } finally {
                checkInFlight.set(false);
            }
        });
    }

    private void applyDecision(AppUpdateDecision decision) {
        if (decision.mode == AppUpdateDecision.Mode.SOFT_FAIL) {
            return;
        }

        if (decision.mode == AppUpdateDecision.Mode.MANDATORY) {
            showMandatory(decision);
            return;
        }

        // Clear mandatory if policy / installed build no longer requires it.
        if (mandatoryVisible) {
            clearMandatoryBlock();
        }

        if (decision.mode == AppUpdateDecision.Mode.OPTIONAL) {
            showOptional(decision);
            return;
        }

        dismissDialog();
    }

    private void showMandatory(AppUpdateDecision decision) {
        ensureDialog();
        mandatoryVisible = true;
        pendingUpdateUrl = decision.updateUrl;
        bindDialog(decision, true);
        setWebViewInteractable(false);
        if (!dialog.isShowing()) {
            dialog.show();
        }
        raiseDialogWindow();
    }

    private void showOptional(AppUpdateDecision decision) {
        if (mandatoryVisible) {
            return;
        }
        ensureDialog();
        pendingUpdateUrl = decision.updateUrl;
        bindDialog(decision, false);
        if (!dialog.isShowing()) {
            dialog.show();
        }
    }

    private void bindDialog(AppUpdateDecision decision, boolean mandatory) {
        TextView title = dialogContent.findViewById(R.id.ghUpdateTitle);
        TextView message = dialogContent.findViewById(R.id.ghUpdateMessage);
        TextView notesView = dialogContent.findViewById(R.id.ghUpdateNotes);
        Button later = dialogContent.findViewById(R.id.ghUpdateLater);
        Button now = dialogContent.findViewById(R.id.ghUpdateNow);

        if (mandatory) {
            title.setText("Update required");
            message.setText("This GNRL Operations build is no longer supported. Install build "
                + decision.latestVersion + " (" + decision.latestBuild + ") to continue.");
            later.setVisibility(View.GONE);
            later.setOnClickListener(null);
        } else {
            title.setText("Update available");
            message.setText("GNRL Operations " + decision.latestVersion
                + " (build " + decision.latestBuild + ") is available.");
            later.setVisibility(View.VISIBLE);
            later.setOnClickListener(v -> {
                activity.getSharedPreferences(PREFS, Activity.MODE_PRIVATE)
                    .edit()
                    .putInt(KEY_OPTIONAL_DISMISSED_BUILD, decision.latestBuild)
                    .apply();
                dismissDialog();
            });
        }

        applyNotes(notesView, decision.notes);
        now.setOnClickListener(v -> openUpdateUrl(pendingUpdateUrl));
    }

    private void applyNotes(TextView notesView, String notes) {
        if (notes == null || notes.trim().isEmpty() || "null".equalsIgnoreCase(notes)) {
            notesView.setVisibility(View.GONE);
            return;
        }
        notesView.setText(notes);
        notesView.setVisibility(View.VISIBLE);
    }

    private void clearMandatoryBlock() {
        mandatoryVisible = false;
        setWebViewInteractable(true);
        dismissDialog();
    }

    private void dismissDialog() {
        if (dialog != null && dialog.isShowing()) {
            dialog.dismiss();
        }
    }

    private void ensureDialog() {
        if (dialog != null) {
            return;
        }

        dialogContent = LayoutInflater.from(activity).inflate(R.layout.view_app_update, null, false);
        dialog = new Dialog(activity, android.R.style.Theme_DeviceDefault_NoActionBar_Fullscreen);
        dialog.requestWindowFeature(Window.FEATURE_NO_TITLE);
        dialog.setContentView(dialogContent);
        dialog.setCancelable(false);
        dialog.setCanceledOnTouchOutside(false);
        dialog.setOnKeyListener((d, keyCode, event) -> {
            if (keyCode == KeyEvent.KEYCODE_BACK) {
                // Mandatory: consume. Optional: treat as Later.
                if (mandatoryVisible) {
                    return true;
                }
                Button later = dialogContent.findViewById(R.id.ghUpdateLater);
                if (later != null && later.getVisibility() == View.VISIBLE) {
                    later.performClick();
                }
                return true;
            }
            return false;
        });

        Window window = dialog.getWindow();
        if (window != null) {
            window.setLayout(WindowManager.LayoutParams.MATCH_PARENT, WindowManager.LayoutParams.MATCH_PARENT);
            window.setBackgroundDrawable(new ColorDrawable(Color.parseColor("#F20D1117")));
            window.addFlags(WindowManager.LayoutParams.FLAG_DIM_BEHIND);
            WindowManager.LayoutParams lp = window.getAttributes();
            lp.dimAmount = 1f;
            window.setAttributes(lp);
        }
    }

    private void raiseDialogWindow() {
        Window window = dialog != null ? dialog.getWindow() : null;
        if (window != null) {
            window.setLayout(WindowManager.LayoutParams.MATCH_PARENT, WindowManager.LayoutParams.MATCH_PARENT);
        }
    }

    private void setWebViewInteractable(boolean enabled) {
        try {
            if (!(activity instanceof MainActivity)) {
                return;
            }
            WebView webView = ((MainActivity) activity).capacitorWebView();
            if (webView == null) {
                return;
            }
            webView.setEnabled(enabled);
            webView.setClickable(enabled);
            webView.setFocusable(enabled);
            webView.setFocusableInTouchMode(enabled);
            if (!enabled) {
                webView.clearFocus();
            }
        } catch (Exception ignored) {
            // Best-effort — Dialog still blocks input.
        }
    }

    private void openUpdateUrl(String updateUrl) {
        if (updateUrl == null || updateUrl.trim().isEmpty() || "null".equalsIgnoreCase(updateUrl)) {
            Toast.makeText(activity, "Update URL is not available", Toast.LENGTH_LONG).show();
            return;
        }
        try {
            Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(updateUrl));
            activity.startActivity(intent);
        } catch (Exception e) {
            Toast.makeText(activity, "Unable to open update URL", Toast.LENGTH_LONG).show();
        }
    }

    private int installedVersionCode() throws PackageManager.NameNotFoundException {
        PackageInfo info = activity.getPackageManager().getPackageInfo(activity.getPackageName(), 0);
        return info.getLongVersionCode() > Integer.MAX_VALUE
            ? Integer.MAX_VALUE
            : (int) info.getLongVersionCode();
    }

    private String installedVersionName() {
        try {
            PackageInfo info = activity.getPackageManager().getPackageInfo(activity.getPackageName(), 0);
            return info.versionName != null ? info.versionName : "";
        } catch (Exception e) {
            return "";
        }
    }

    private String readServerBase() throws Exception {
        try (InputStream in = activity.getAssets().open("capacitor.config.json");
             BufferedReader reader = new BufferedReader(new InputStreamReader(in, StandardCharsets.UTF_8))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line);
            }
            JSONObject root = new JSONObject(sb.toString());
            String url = root.getJSONObject("server").optString("url", "");
            Uri uri = Uri.parse(url);
            String scheme = uri.getScheme() != null ? uri.getScheme() : "https";
            String host = uri.getHost();
            if (host == null || host.isEmpty()) {
                throw new IllegalStateException("missing host");
            }
            int port = uri.getPort();
            if (port > 0) {
                return scheme + "://" + host + ":" + port;
            }
            return scheme + "://" + host;
        }
    }

    private static String readAll(InputStream stream) throws Exception {
        if (stream == null) {
            return "";
        }
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(stream, StandardCharsets.UTF_8))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line);
            }
            return sb.toString();
        }
    }
}
