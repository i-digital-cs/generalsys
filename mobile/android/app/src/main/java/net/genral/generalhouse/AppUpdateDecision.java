package net.genral.generalhouse;

/**
 * Pure decision helper for version-check payloads.
 * Keeps UI enforcement rules testable without an Android device.
 */
final class AppUpdateDecision {
    enum Mode {
        NONE,
        OPTIONAL,
        MANDATORY,
        SOFT_FAIL
    }

    final Mode mode;
    final String latestVersion;
    final int latestBuild;
    final String updateUrl;
    final String notes;

    private AppUpdateDecision(Mode mode, String latestVersion, int latestBuild, String updateUrl, String notes) {
        this.mode = mode;
        this.latestVersion = latestVersion == null ? "" : latestVersion;
        this.latestBuild = latestBuild;
        this.updateUrl = updateUrl == null ? "" : updateUrl;
        this.notes = notes == null ? "" : notes;
    }

    static AppUpdateDecision none() {
        return new AppUpdateDecision(Mode.NONE, "", 0, "", "");
    }

    static AppUpdateDecision softFail() {
        return new AppUpdateDecision(Mode.SOFT_FAIL, "", 0, "", "");
    }

    /**
     * @param serviceOk           API service_ok
     * @param updateRequired      API update_required (server already enforces min/latest/APK rules)
     * @param updateAvailable     API update_available
     * @param latestBuild         API latest_build
     * @param latestVersion       API latest_version
     * @param updateUrl           API update_url
     * @param notes               API release_notes
     * @param optionalDismissedBuild previously dismissed optional latest_build (0 = none)
     */
    static AppUpdateDecision evaluate(
        boolean serviceOk,
        boolean updateRequired,
        boolean updateAvailable,
        int latestBuild,
        String latestVersion,
        String updateUrl,
        String notes,
        int optionalDismissedBuild
    ) {
        if (!serviceOk) {
            return softFail();
        }

        if (updateRequired) {
            return new AppUpdateDecision(Mode.MANDATORY, latestVersion, latestBuild, updateUrl, notes);
        }

        if (updateAvailable && latestBuild > 0 && optionalDismissedBuild != latestBuild) {
            return new AppUpdateDecision(Mode.OPTIONAL, latestVersion, latestBuild, updateUrl, notes);
        }

        return none();
    }

    boolean isBlocking() {
        return mode == Mode.MANDATORY;
    }

    boolean hasUsableUpdateUrl() {
        String url = updateUrl.trim();
        return !url.isEmpty() && !"null".equalsIgnoreCase(url);
    }
}
