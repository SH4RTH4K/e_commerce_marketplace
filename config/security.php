<?php

return [
    // Keep report-only available for a controlled rollout; enforcement is the
    // default once the nonce coverage has been verified in production.
    'csp_report_only' => (bool) env('CSP_REPORT_ONLY', false),
];
