# Connecting a saved dropshipping supplier

Local follow-up to the user's Test Supplier (ID 2) configuration issue.

## Cause

The supplier was saved correctly with business type `dropshipping` and driver `manual`. Business type alone intentionally does not enable an API integration: manual dropshipping is supported. The overview showed only configured integrations, and the supplier pages lacked a direct connection action. API Settings also opened the existing Mohasagor account by default, making the new supplier difficult to find.

## Changes

- Active unconfigured Dropshipping suppliers now appear under **Suppliers ready for API setup** on the overview.
- Supplier list, details and edit screens show **Set up API connection**, subject to Dropshipping permissions and the integration feature flag.
- The action opens `/admin/dropshipping/settings?connect_supplier_id=2` with the existing Test Supplier selected. Core supplier forms continue to exclude API credentials.
- Inactive, missing and already configured targets are rejected. Existing connections have their own settings link.
- Saving the connection updates the selected business supplier rather than creating a duplicate. The server redirects to that exact account, including when other accounts have greater IDs. Business type, supplier code and manual product ownership remain intact.
- The provider must be selected explicitly. URL and credentials are supplied by the administrator; no credentials are copied from Mohasagor.
- While creating the connection, the setup navigation retains Test Supplier and disables later steps instead of opening another supplier's products. Switching the connection target resets the unsaved connection form.

## Files

- Controllers: `Admin/DropshippingController.php`, `Admin/SupplierController.php`.
- Dropshipping pages: `Index.jsx`, `ApiSettings.jsx`, `Subpage.jsx`.
- Supplier pages: `Index.jsx`, `Show.jsx`, `Form.jsx`.
- Tests: `DropshippingSetupExperienceTest.php`, `SupplierConnectionEntrypointsTest.php`, `DropshippingSetup.test.mjs`, `SupplierPages.test.mjs`.
- Regenerated `public/build/` assets.

## Validation

- Related PHP tests: **33 passed, 620 assertions**.
- Component suite: **60 passed**.
- Asset build and whitespace checks passed; existing template-1 font warnings remain.
- Browser verification on the actual local application: Test Supplier edit page → setup action → connection form preselecting supplier ID 2. Overview also displays the pending supplier beside the working Mohasagor account.
- Screenshot: `supplier-review/test-supplier-api-setup-local.png`.

No migration or local database change was required. Test Supplier is still unconnected until its actual API access is entered and saved. The browser verification did not submit credentials, create a connection, run an external API test, or start a sync. The existing Mohasagor setup and product records remain in place. This change has not been deployed live.
