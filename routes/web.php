<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customers\CustomerAddressController;
use App\Http\Controllers\Customers\CustomerContactController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\CustomerServiceController;
use App\Http\Controllers\Customers\CustomerPortalAccountController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalInvoiceController;
use App\Http\Controllers\Portal\PortalPaymentController;
use App\Http\Controllers\Portal\PortalProfileController;
use App\Http\Controllers\Portal\PortalReceiptController;
use App\Http\Controllers\Portal\PortalTicketController;
use App\Http\Controllers\FieldOps\FieldOperationsController;
use App\Http\Controllers\FieldOps\TechnicianController;
use App\Http\Controllers\FieldOps\TicketController;
use App\Http\Controllers\FieldOps\WorkOrderController;
use App\Http\Controllers\FieldOps\InstallationController;
use App\Http\Controllers\FieldOps\InventoryController;
use App\Http\Controllers\FieldOps\NetworkNodeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\Billing\ReceiptController;
use App\Http\Controllers\Billing\PaymentGatewayController;
use App\Http\Controllers\Integrations\IntegrationsController;
use App\Http\Controllers\Network\IpPoolController;
use App\Http\Controllers\Network\NasController;
use App\Http\Controllers\Network\NetworkController;
use App\Http\Controllers\Network\PlanController;
use App\Http\Controllers\Network\RadiusTestController;
use App\Http\Controllers\Network\RouterController;
use App\Http\Controllers\Network\SessionController;
use App\Http\Controllers\Operations\AutomationController;
use App\Http\Controllers\Reports\ReportsController;
use App\Http\Controllers\System\HealthController;
use App\Http\Controllers\System\SystemController;
use App\Http\Controllers\System\WebhookController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\Platform\PlatformPlanController;
use App\Http\Controllers\Platform\VersionController;
use App\Http\Controllers\Platform\ReleaseCenterController;
use App\Http\Controllers\Partner\PartnerAuthController;
use App\Http\Controllers\Partner\PartnerDashboardController;
use App\Http\Controllers\Partner\PartnerCustomerController;
use App\Http\Controllers\Partner\PartnerBillingController;
use App\Http\Controllers\Partner\PartnerCommissionController;
use App\Http\Controllers\Partner\PartnerTicketController;
use App\Http\Controllers\Partners\PartnersController;
use App\Http\Controllers\Inventory\InventoryAuthController;
use App\Http\Controllers\Inventory\InventoryDashboardController;
use App\Http\Controllers\Inventory\InventoryCatalogController;
use App\Http\Controllers\Inventory\InventoryTransactionController;
use App\Http\Controllers\Inventory\InventoryPurchaseController;
use App\Http\Controllers\Inventory\InventoryAdminController;
use App\Http\Controllers\Commercial\AccessController;
use App\Http\Controllers\Commercial\SettingsController;
use App\Http\Controllers\Commercial\ManualPaymentProofController;
use App\Http\Controllers\Portal\PortalManualPaymentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => auth()->check() ? redirect('/dashboard') : redirect('/login'));
Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');
Route::get('/version', VersionController::class)->name('version');
Route::get('/access', AccessController::class)->name('access.center');
Route::get('/portal', fn () => Inertia::render('Portal/Landing'))->name('portal.landing');
Route::get('/portal/tenant/login', fn () => redirect('/access?portal=customer'))->name('portal.placeholder');


Route::prefix('/portal/{tenantSlug}')->group(function () {
    Route::get('/', fn (string $tenantSlug) => redirect()->route('portal.dashboard', ['tenantSlug' => $tenantSlug]));
    Route::get('/login', [PortalAuthController::class, 'create'])->name('portal.login');
    Route::post('/login', [PortalAuthController::class, 'store'])->middleware('throttle:30,1')->name('portal.login.store');

    Route::middleware('portal.auth')->group(function () {
        Route::get('/dashboard', PortalDashboardController::class)->name('portal.dashboard');
        Route::post('/logout', [PortalAuthController::class, 'destroy'])->name('portal.logout');
        Route::get('/profile', [PortalProfileController::class, 'show'])->name('portal.profile');
        Route::put('/profile/password', [PortalProfileController::class, 'password'])->name('portal.profile.password');
        Route::get('/invoices/{invoice}', [PortalInvoiceController::class, 'show'])->name('portal.invoices.show');
        Route::get('/invoices/{invoice}/download', [PortalInvoiceController::class, 'download'])->name('portal.invoices.download');
        Route::post('/invoices/{invoice}/payment', [PortalPaymentController::class, 'initiate'])->name('portal.invoices.payment');
        Route::post('/invoices/{invoice}/manual-payment', [PortalManualPaymentController::class, 'store'])->name('portal.invoices.manual-payment');
        Route::get('/gateway/{transaction}/mock', [PortalPaymentController::class, 'mock'])->name('portal.gateway.mock');
        Route::post('/gateway/{transaction}/mock-settle', [PortalPaymentController::class, 'settleMock'])->name('portal.gateway.mock-settle');
        Route::get('/receipts/{payment}/download', [PortalReceiptController::class, 'download'])->name('portal.receipts.download');
        Route::get('/tickets', [PortalTicketController::class, 'index'])->name('portal.tickets.index');
        Route::post('/tickets', [PortalTicketController::class, 'store'])->name('portal.tickets.store');
        Route::post('/tickets/{ticket}/comments', [PortalTicketController::class, 'comment'])->name('portal.tickets.comment');
    });
});


Route::get('/mitra', fn () => Inertia::render('Partner/Landing'))->name('partner.landing');
Route::get('/mitra/tenant/login', fn () => redirect('/access?portal=partner'))->name('partner.placeholder');
Route::prefix('/mitra/{tenantSlug}')->group(function () {
    Route::get('/login', [PartnerAuthController::class, 'create'])->name('partner.login');
    Route::post('/login', [PartnerAuthController::class, 'store'])->middleware('throttle:30,1')->name('partner.login.store');
    Route::middleware('partner.auth')->group(function () {
        Route::get('/dashboard', PartnerDashboardController::class)->name('partner.dashboard');
        Route::post('/logout', [PartnerAuthController::class, 'destroy'])->name('partner.logout');
        Route::get('/customers', [PartnerCustomerController::class, 'index'])->name('partner.customers.index');
        Route::post('/customers', [PartnerCustomerController::class, 'store'])->name('partner.customers.store');
        Route::get('/billing', [PartnerBillingController::class, 'index'])->name('partner.billing.index');
        Route::post('/billing/invoices/{invoice}/payments', [PartnerBillingController::class, 'pay'])->name('partner.billing.pay');
        Route::get('/commissions', [PartnerCommissionController::class, 'index'])->name('partner.commissions.index');
        Route::post('/commissions/withdrawals', [PartnerCommissionController::class, 'requestWithdrawal'])->name('partner.withdrawals.store');
        Route::get('/tickets', [PartnerTicketController::class, 'index'])->name('partner.tickets.index');
        Route::post('/tickets', [PartnerTicketController::class, 'store'])->name('partner.tickets.store');
    });
});


Route::get('/inventory', fn () => Inertia::render('Inventory/Landing'))->name('inventory.landing');
Route::get('/inventory/tenant/login', fn () => redirect('/access?portal=inventory'))->name('inventory.placeholder');
Route::prefix('/inventory/{tenantSlug}')->group(function () {
    Route::get('/login', [InventoryAuthController::class, 'create'])->name('inventory.login');
    Route::post('/login', [InventoryAuthController::class, 'store'])->middleware('throttle:30,1')->name('inventory.login.store');
    Route::middleware('inventory.auth')->group(function () {
        Route::get('/dashboard', InventoryDashboardController::class)->name('inventory.dashboard');
        Route::post('/logout', [InventoryAuthController::class, 'destroy'])->name('inventory.logout');
        Route::put('/password', [InventoryAuthController::class, 'password'])->name('inventory.password');
        Route::post('/skus', [InventoryCatalogController::class, 'sku'])->name('inventory.skus.store');
        Route::post('/suppliers', [InventoryCatalogController::class, 'supplier'])->name('inventory.suppliers.store');
        Route::post('/receive', [InventoryTransactionController::class, 'receive'])->name('inventory.receive');
        Route::post('/transfer', [InventoryTransactionController::class, 'transfer'])->name('inventory.transfer');
        Route::post('/install', [InventoryTransactionController::class, 'install'])->name('inventory.install');
        Route::post('/retrieve', [InventoryTransactionController::class, 'retrieve'])->name('inventory.retrieve');
        Route::post('/stock-opname', [InventoryTransactionController::class, 'opname'])->name('inventory.opname');
        Route::post('/purchases', [InventoryPurchaseController::class, 'store'])->name('inventory.purchases.store');
        Route::post('/purchases/{purchase}/receive', [InventoryPurchaseController::class, 'receive'])->name('inventory.purchases.receive');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware(['auth', 'platform-admin'])->prefix('platform')->group(function () {
    Route::get('/', [PlatformController::class, 'index'])->name('platform.index');
    Route::post('/tenants', [PlatformController::class, 'storeTenant'])->name('platform.tenants.store');
    Route::put('/tenants/{tenant}/subscription', [PlatformController::class, 'updateSubscription'])->name('platform.tenants.subscription');
    Route::put('/tenants/{tenant}/status', [PlatformController::class, 'updateTenantStatus'])->name('platform.tenants.status');
    Route::put('/plans/{plan}', [PlatformPlanController::class, 'update'])->name('platform.plans.update');
    Route::get('/release', [ReleaseCenterController::class, 'index'])->name('platform.release');
    Route::post('/release/audit', [ReleaseCenterController::class, 'audit'])->name('platform.release.audit');
});

Route::middleware(['auth', 'tenant', 'subscription'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

    Route::middleware('system-admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/branding', [SettingsController::class, 'updateBranding'])->name('settings.branding.update');
        Route::post('/settings/payment-methods', [SettingsController::class, 'storePaymentMethod'])->name('settings.payment-methods.store');
        Route::post('/settings/payment-methods/{method}', [SettingsController::class, 'updatePaymentMethod'])->name('settings.payment-methods.update');
        Route::delete('/settings/payment-methods/{method}', [SettingsController::class, 'destroyPaymentMethod'])->name('settings.payment-methods.destroy');
        Route::post('/settings/roles', [SettingsController::class, 'storeRole'])->name('settings.roles.store');
        Route::put('/settings/roles/{role}', [SettingsController::class, 'updateRole'])->name('settings.roles.update');
        Route::delete('/settings/roles/{role}', [SettingsController::class, 'destroyRole'])->name('settings.roles.destroy');
        Route::post('/settings/users', [SettingsController::class, 'storeUser'])->middleware('subscription.limit:users')->name('settings.users.store');
        Route::put('/settings/users/{user}', [SettingsController::class, 'updateUser'])->name('settings.users.update');
        Route::post('/settings/users/{user}/reset-password', [SettingsController::class, 'resetUserPassword'])->name('settings.users.reset-password');
        Route::delete('/settings/users/{user}', [SettingsController::class, 'removeUser'])->name('settings.users.remove');
    });

    Route::get('/billing/manual-payments', [ManualPaymentProofController::class, 'index'])->middleware('permission:billing.view')->name('billing.manual-payments.index');
    Route::put('/billing/manual-payments/{proof}', [ManualPaymentProofController::class, 'review'])->middleware('permission:billing.manage')->name('billing.manual-payments.review');
    Route::get('/billing/manual-payments/{proof}/file', [ManualPaymentProofController::class, 'file'])->middleware('permission:billing.view')->name('billing.manual-payments.file');

    Route::get('/inventory-management', [InventoryAdminController::class, 'index'])->middleware('permission:inventory.view')->name('inventory-admin.index');
    Route::post('/inventory-management/locations', [InventoryAdminController::class, 'location'])->middleware('permission:inventory.manage')->name('inventory-admin.locations.store');
    Route::post('/inventory-management/accounts', [InventoryAdminController::class, 'account'])->middleware('permission:inventory.manage')->name('inventory-admin.accounts.store');
    Route::put('/inventory-management/accounts/{account}/status', [InventoryAdminController::class, 'status'])->middleware('permission:inventory.manage')->name('inventory-admin.accounts.status');

    Route::get('/partners', [PartnersController::class, 'index'])->middleware('permission:partners.view')->name('partners.index');
    Route::post('/partners', [PartnersController::class, 'store'])->middleware('permission:partners.manage')->name('partners.store');
    Route::post('/partners/{partner}/accounts', [PartnersController::class, 'account'])->middleware('permission:partners.manage')->name('partners.accounts.store');
    Route::post('/partners/{partner}/commission-rules', [PartnersController::class, 'rule'])->middleware('permission:partners.manage')->name('partners.rules.store');
    Route::post('/partners/{partner}/customers', [PartnersController::class, 'assign'])->middleware('permission:partners.manage')->name('partners.customers.assign');
    Route::put('/partner-withdrawals/{withdrawal}', [PartnersController::class, 'withdrawal'])->middleware('permission:partners.manage')->name('partners.withdrawals.update');

    
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware(['subscription.limit:customers','permission:customers.manage'])->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view')->name('customers.show');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.manage')->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.manage')->name('customers.destroy');

    Route::post('/customers/{customer}/addresses', [CustomerAddressController::class, 'store'])->middleware('permission:customers.manage')->name('customers.addresses.store');
    Route::delete('/customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'destroy'])->middleware('permission:customers.manage')->name('customers.addresses.destroy');
    Route::post('/customers/{customer}/contacts', [CustomerContactController::class, 'store'])->middleware('permission:customers.manage')->name('customers.contacts.store');
    Route::delete('/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])->middleware('permission:customers.manage')->name('customers.contacts.destroy');

    Route::post('/customers/{customer}/services', [CustomerServiceController::class, 'store'])->middleware(['subscription.limit:services','permission:customers.manage'])->name('customers.services.store');
    Route::put('/customers/{customer}/services/{service}', [CustomerServiceController::class, 'update'])->middleware('permission:customers.manage')->name('customers.services.update');
    Route::post('/customers/{customer}/services/{service}/radius-sync', [CustomerServiceController::class, 'sync'])->middleware('permission:customers.manage')->name('customers.services.radius-sync');
    Route::delete('/customers/{customer}/services/{service}', [CustomerServiceController::class, 'destroy'])->middleware('permission:customers.manage')->name('customers.services.destroy');

    Route::post('/customers/{customer}/portal-account', [CustomerPortalAccountController::class, 'store'])->middleware('permission:customers.manage')->name('customers.portal.store');
    Route::post('/customers/{customer}/portal-account/reset-password', [CustomerPortalAccountController::class, 'resetPassword'])->middleware('permission:customers.manage')->name('customers.portal.reset-password');
    Route::put('/customers/{customer}/portal-account/status', [CustomerPortalAccountController::class, 'updateStatus'])->middleware('permission:customers.manage')->name('customers.portal.status');

    Route::get('/network', NetworkController::class)->middleware('permission:network.view')->name('network.index');
    Route::post('/network/routers', [RouterController::class, 'store'])->middleware(['subscription.limit:routers','permission:network.manage'])->name('network.routers.store');
    Route::post('/network/routers/{router}/test', [RouterController::class, 'test'])->middleware('permission:network.manage')->name('network.routers.test');
    Route::delete('/network/routers/{router}', [RouterController::class, 'destroy'])->middleware('permission:network.manage')->name('network.routers.destroy');
    Route::post('/network/nas', [NasController::class, 'store'])->middleware('permission:network.manage')->name('network.nas.store');
    Route::post('/network/nas/{nas}/sync', [NasController::class, 'sync'])->middleware('permission:network.manage')->name('network.nas.sync');
    Route::delete('/network/nas/{nas}', [NasController::class, 'destroy'])->middleware('permission:network.manage')->name('network.nas.destroy');
    Route::post('/network/plans', [PlanController::class, 'store'])->middleware('permission:network.manage')->name('network.plans.store');
    Route::delete('/network/plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:network.manage')->name('network.plans.destroy');
    Route::post('/network/pools', [IpPoolController::class, 'store'])->middleware('permission:network.manage')->name('network.pools.store');
    Route::delete('/network/pools/{pool}', [IpPoolController::class, 'destroy'])->middleware('permission:network.manage')->name('network.pools.destroy');
    Route::post('/network/radius/test', RadiusTestController::class)->middleware('permission:network.manage')->name('network.radius.test');

    Route::get('/network/sessions', [SessionController::class, 'index'])->middleware('permission:network.view')->name('network.sessions.index');
    Route::post('/network/sessions/{session}/disconnect', [SessionController::class, 'disconnect'])->middleware('permission:network.manage')->name('network.sessions.disconnect');
    Route::post('/network/sessions/{session}/coa', [SessionController::class, 'coa'])->middleware('permission:network.manage')->name('network.sessions.coa');

    Route::get('/billing', [BillingController::class, 'index'])->middleware('permission:billing.view')->name('billing.index');
    Route::post('/billing/run', [BillingController::class, 'run'])->middleware('permission:billing.manage')->name('billing.run');
    Route::get('/billing/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:billing.view')->name('billing.invoices.show');
    Route::get('/billing/invoices/{invoice}/download', [InvoiceController::class, 'download'])->middleware('permission:billing.view')->name('billing.invoices.download');
    Route::get('/billing/payments/{payment}/receipt', [ReceiptController::class, 'download'])->middleware('permission:billing.view')->name('billing.payments.receipt');
    Route::post('/billing/invoices/{invoice}/payments', [PaymentController::class, 'store'])->middleware('permission:billing.manage')->name('billing.invoices.payments.store');
    Route::post('/billing/invoices/{invoice}/gateway', [PaymentGatewayController::class, 'store'])->middleware('permission:billing.manage')->name('billing.invoices.gateway.store');
    Route::get('/billing/gateway-transactions/{transaction}/mock', [PaymentGatewayController::class, 'mock'])->middleware('permission:billing.manage')->name('billing.gateway.mock');
    Route::post('/billing/gateway-transactions/{transaction}/mock-settle', [PaymentGatewayController::class, 'settleMock'])->middleware('permission:billing.manage')->name('billing.gateway.mock-settle');
    Route::post('/billing/gateway-transactions/{transaction}/refresh', [PaymentGatewayController::class, 'refresh'])->middleware('permission:billing.manage')->name('billing.gateway.refresh');
    Route::get('/field-operations', FieldOperationsController::class)->middleware('permission:field_ops.view')->name('field-operations.index');
    Route::post('/field-operations/technicians', [TechnicianController::class, 'store'])->middleware('permission:field_ops.manage')->name('field-operations.technicians.store');
    Route::put('/field-operations/technicians/{technician}', [TechnicianController::class, 'update'])->middleware('permission:field_ops.manage')->name('field-operations.technicians.update');
    Route::post('/field-operations/tickets', [TicketController::class, 'store'])->middleware('permission:field_ops.manage')->name('field-operations.tickets.store');
    Route::put('/field-operations/tickets/{ticket}/status', [TicketController::class, 'status'])->middleware('permission:field_ops.manage')->name('field-operations.tickets.status');
    Route::post('/field-operations/tickets/{ticket}/comments', [TicketController::class, 'comment'])->middleware('permission:field_ops.manage')->name('field-operations.tickets.comment');
    Route::post('/field-operations/work-orders', [WorkOrderController::class, 'store'])->middleware('permission:field_ops.manage')->name('field-operations.work-orders.store');
    Route::put('/field-operations/work-orders/{workOrder}/status', [WorkOrderController::class, 'status'])->middleware('permission:field_ops.manage')->name('field-operations.work-orders.status');
    Route::post('/field-operations/installations', [InstallationController::class, 'store'])->middleware('permission:field_ops.manage')->name('field-operations.installations.store');
    Route::put('/field-operations/installations/{installation}/status', [InstallationController::class, 'status'])->middleware('permission:field_ops.manage')->name('field-operations.installations.status');
    Route::post('/field-operations/inventory', [InventoryController::class, 'store'])->middleware('permission:field_ops.manage')->name('field-operations.inventory.store');
    Route::put('/field-operations/inventory/{item}/assign', [InventoryController::class, 'assign'])->middleware('permission:field_ops.manage')->name('field-operations.inventory.assign');
    Route::post('/field-operations/network-nodes', [NetworkNodeController::class, 'store'])->middleware('permission:field_ops.manage')->name('field-operations.nodes.store');
    Route::post('/field-operations/network-assignments', [NetworkNodeController::class, 'assignService'])->middleware('permission:field_ops.manage')->name('field-operations.nodes.assign-service');

    Route::get('/operations', [AutomationController::class, 'index'])->middleware('permission:operations.view')->name('operations.index');
    Route::put('/operations/policy', [AutomationController::class, 'updatePolicy'])->middleware('permission:operations.manage')->name('operations.policy.update');
    Route::post('/operations/run', [AutomationController::class, 'run'])->middleware('permission:operations.manage')->name('operations.run');
    Route::get('/reports', [ReportsController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('/reports/export/{type}', [ReportsController::class, 'export'])->middleware('permission:reports.view')->name('reports.export');

    Route::middleware('system-admin')->group(function () {
        Route::get('/system', [SystemController::class, 'index'])->name('system.index');
        Route::get('/integrations', [IntegrationsController::class, 'index'])->name('integrations.index');
        Route::put('/integrations/payment', [IntegrationsController::class, 'updatePayment'])->name('integrations.payment.update');
        Route::put('/integrations/whatsapp', [IntegrationsController::class, 'updateWhatsApp'])->name('integrations.whatsapp.update');
        Route::post('/integrations/whatsapp/test', [IntegrationsController::class, 'testWhatsApp'])->name('integrations.whatsapp.test');
        Route::post('/system/notifications/test', [SystemController::class, 'testNotification'])->name('system.notifications.test');
        Route::post('/system/webhooks', [WebhookController::class, 'store'])->name('system.webhooks.store');
        Route::put('/system/webhooks/{webhookId}', [WebhookController::class, 'update'])->name('system.webhooks.update');
        Route::delete('/system/webhooks/{webhookId}', [WebhookController::class, 'destroy'])->name('system.webhooks.destroy');
        Route::post('/system/webhooks/{webhookId}/test', [WebhookController::class, 'test'])->name('system.webhooks.test');
    });
});
