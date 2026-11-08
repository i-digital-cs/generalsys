package net.genral.generalhouse;

import org.junit.Test;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertTrue;

public class AppUpdateDecisionTest {

    @Test
    public void noUpdateWhenCurrentAtLatest() {
        AppUpdateDecision d = AppUpdateDecision.evaluate(
            true, false, false, 5, "1.0.4", "https://example.test/apk", null, 0
        );
        assertEquals(AppUpdateDecision.Mode.NONE, d.mode);
        assertFalse(d.isBlocking());
    }

    @Test
    public void optionalWhenBehindLatestButAboveMinimum() {
        AppUpdateDecision d = AppUpdateDecision.evaluate(
            true, false, true, 5, "1.0.4", "https://example.test/apk", "notes", 0
        );
        assertEquals(AppUpdateDecision.Mode.OPTIONAL, d.mode);
        assertFalse(d.isBlocking());
        assertTrue(d.hasUsableUpdateUrl());
    }

    @Test
    public void optionalDismissedForSameLatestBuildIsNone() {
        AppUpdateDecision d = AppUpdateDecision.evaluate(
            true, false, true, 5, "1.0.4", "https://example.test/apk", null, 5
        );
        assertEquals(AppUpdateDecision.Mode.NONE, d.mode);
    }

    @Test
    public void mandatoryWhenServerRequiresUpdate() {
        AppUpdateDecision d = AppUpdateDecision.evaluate(
            true, true, true, 5, "1.0.4", "https://example.test/apk", "Security", 0
        );
        assertEquals(AppUpdateDecision.Mode.MANDATORY, d.mode);
        assertTrue(d.isBlocking());
        assertEquals(5, d.latestBuild);
    }

    @Test
    public void mandatoryIgnoresOptionalDismissal() {
        AppUpdateDecision d = AppUpdateDecision.evaluate(
            true, true, true, 5, "1.0.4", "https://example.test/apk", null, 5
        );
        assertEquals(AppUpdateDecision.Mode.MANDATORY, d.mode);
        assertTrue(d.isBlocking());
    }

    @Test
    public void softFailWhenServiceNotOk() {
        AppUpdateDecision d = AppUpdateDecision.evaluate(
            false, true, true, 5, "1.0.4", "https://example.test/apk", null, 0
        );
        assertEquals(AppUpdateDecision.Mode.SOFT_FAIL, d.mode);
        assertFalse(d.isBlocking());
    }

    @Test
    public void emptyUpdateUrlIsNotUsable() {
        AppUpdateDecision d = AppUpdateDecision.evaluate(
            true, true, true, 5, "1.0.4", "null", null, 0
        );
        assertTrue(d.isBlocking());
        assertFalse(d.hasUsableUpdateUrl());
    }
}
