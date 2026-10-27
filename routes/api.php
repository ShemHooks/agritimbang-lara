<?php

use App\Http\Controllers\{
    AuctionHouseTransactionController,
    AuthController,
    BarangayController,
    BarangayGeometryController,
    BreedController,
    BuyerConfirmationController,
    FarmerLivestockController,
    FarmerProfileController,
    FarmerTransactionController,
    FarmerValuationController,
    FarmerWeightEstimationController,
    GisController,
    LguAuctionHouseController,
    LguAuthorityController,
    LguEncoderController,
    LguLivestockController,
    LguTransactionVerificationController,
    LguValuationController,
    LguWeightEstimationController,
    MunicipalityController,
    PriceReferenceController,
    PriceValidationController,
    SpeciesController,
    TransactionCancellationController,
    TransactionDisputeController,
    UserVerificationController,
    DashboardController,
    PriceAnalyticsController,
    ForecastController,
    ReportController,
    AuditLogController,
    OtpController,
    NotificationController,
    PasswordResetController
};
use Illuminate\Support\Facades\Route;







/*
|--------------------------------------------------------------------------
| 01. Public authentication
|--------------------------------------------------------------------------
| Registration and login are public. Account and logout require Sanctum.
*/
Route::prefix('auth')->group(function () {
    Route::post('register/farmer', [AuthController::class, 'registerFarmer']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::post('/auth/forgot-password', [
    PasswordResetController::class,
    'forgot'
])->middleware('throttle:3,1');

Route::post('/auth/reset-password', [
    PasswordResetController::class,
    'reset'
])->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| 02. Public reference data
|--------------------------------------------------------------------------
| Lookup endpoints used by registration and selection forms.
*/
Route::prefix('reference')->group(function () {
    Route::get('municipalities', [MunicipalityController::class, 'referenceIndex']);
    Route::get('municipalities/{municipality}/barangays', [BarangayController::class, 'referenceByMunicipality']);
    Route::get('species', [SpeciesController::class, 'referenceIndex']);
    Route::get('species/{species}/breeds', [BreedController::class, 'referenceBySpecies']);
});

/*
|--------------------------------------------------------------------------
| 03. Buyer confirmation links
|--------------------------------------------------------------------------
| Token-based confirmation, throttled independently of login.
*/
Route::prefix('buyer-confirmations')->middleware('throttle:10,1')->group(function () {
    Route::get('{token}', [BuyerConfirmationController::class, 'show']);
    Route::post('{token}/confirm', [BuyerConfirmationController::class, 'confirm']);
    Route::post('{token}/dispute', [BuyerConfirmationController::class, 'dispute']);
});

/*
|--------------------------------------------------------------------------
| 04. GIS Monitoring
|--------------------------------------------------------------------------
*/

Route::prefix('gis')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Geographic Reference Data
    |--------------------------------------------------------------------------
    */

    Route::get('municipalities', [GisController::class, 'municipalities']);

    Route::get('barangays', [GisController::class, 'barangays']);

    Route::get('geojson', [GisController::class, 'geojson']);

    /*
    |--------------------------------------------------------------------------
    | Authenticated Livestock Monitoring
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'role:admin,lgu_authority,lgu_encoder',
    ])->group(function () {

        Route::get(
            'monitoring-geojson',
            [GisController::class, 'monitoringGeojson']
        );

        Route::get(
            'livestock-summary',
            [GisController::class, 'livestockSummary']
        );
    });
});

Route::middleware('auth:sanctum')
    ->prefix('gis')
    ->group(function () {
        Route::get(
            'livestock-summary',
            [GisController::class, 'livestockSummary']
        );
    });

/*
|--------------------------------------------------------------------------
| 05. Authenticated shared lookups
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('municipalities/active', [MunicipalityController::class, 'active']);
});

/*
|--------------------------------------------------------------------------
| 06. System administrator
|--------------------------------------------------------------------------
| Global reference-data management and LGU authority administration.
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Municipalities: activation routes precede resource model binding.
    Route::patch('municipalities/{municipality}/activate', [MunicipalityController::class, 'activate']);
    Route::patch('municipalities/{municipality}/deactivate', [MunicipalityController::class, 'destroy']);
    Route::apiResource('municipalities', MunicipalityController::class);

    // LGU authorities.
    Route::prefix('lgu-authorities')->group(function () {
        Route::get('/', [LguAuthorityController::class, 'index']);
        Route::post('/', [LguAuthorityController::class, 'store']);
        Route::get('{authority}', [LguAuthorityController::class, 'show']);
        Route::patch('{authority}', [LguAuthorityController::class, 'update']);
        Route::patch('{authority}/status', [LguAuthorityController::class, 'updateStatus']);
    });

    // Barangays and their imported GIS geometries.
    Route::get('municipalities/{municipality}/barangays', [BarangayController::class, 'indexByMunicipality']);
    Route::prefix('barangays')->group(function () {
        Route::get('/', [BarangayController::class, 'index']);
        Route::post('/', [BarangayController::class, 'store']);
        Route::get('{barangay}', [BarangayController::class, 'show']);
        Route::patch('{barangay}', [BarangayController::class, 'update']);
        Route::patch('{barangay}/status', [BarangayController::class, 'updateStatus']);
        Route::get('{barangay}/geometry', [BarangayGeometryController::class, 'show']);
        Route::put('{barangay}/geometry', [BarangayGeometryController::class, 'upsert']);
    });

    // Species reference management.
    Route::prefix('species')->group(function () {
        Route::get('/', [SpeciesController::class, 'index']);
        Route::post('/', [SpeciesController::class, 'store']);
        Route::get('{species}', [SpeciesController::class, 'show']);
        Route::patch('{species}', [SpeciesController::class, 'update']);
        Route::patch('{species}/status', [SpeciesController::class, 'updateStatus']);
    });

    // Breed reference management.
    Route::prefix('breeds')->group(function () {
        Route::get('/', [BreedController::class, 'index']);
        Route::post('/', [BreedController::class, 'store']);
        Route::get('{breed}', [BreedController::class, 'show']);
        Route::patch('{breed}', [BreedController::class, 'update']);
        Route::patch('{breed}/status', [BreedController::class, 'updateStatus']);
    });
});

/*
|--------------------------------------------------------------------------
| 07. LGU authority
|--------------------------------------------------------------------------
| Municipality-level supervision, verification, and price approval.
*/
Route::prefix('lgu')->middleware(['auth:sanctum', 'role:lgu_authority'])->group(function () {
    // Encoder accounts.
    Route::prefix('encoders')->group(function () {
        Route::get('/', [LguEncoderController::class, 'index']);
        Route::post('/', [LguEncoderController::class, 'store']);
        Route::get('{encoder}', [LguEncoderController::class, 'show']);
        Route::patch('{encoder}', [LguEncoderController::class, 'update']);
        Route::patch('{encoder}/status', [LguEncoderController::class, 'updateStatus']);

    });

    // Farmer verification and private document review.
    Route::get('farmer-verifications', [UserVerificationController::class, 'pending']);
    Route::get('farmer-verifications/{farmer}', [UserVerificationController::class, 'review']);
    Route::get('verification-documents/{document}/file', [UserVerificationController::class, 'reviewDocumentFile']);
    Route::post('verification-documents/{document}/approve', [UserVerificationController::class, 'approve']);
    Route::post('verification-documents/{document}/reject', [UserVerificationController::class, 'reject']);

    // Official price validation.
    Route::prefix('price-validations')->group(function () {
        Route::get('/', [PriceValidationController::class, 'index']);
        Route::get('{priceReference}', [PriceValidationController::class, 'show']);
        Route::post('{priceReference}/approve', [PriceValidationController::class, 'approve']);
        Route::post('{priceReference}/reject', [PriceValidationController::class, 'reject']);
    });
});

/*
|--------------------------------------------------------------------------
| 08. LGU encoder
|--------------------------------------------------------------------------
| Price drafting, livestock encoding, weight estimates, and valuations.
*/
Route::prefix('lgu')->middleware(['auth:sanctum', 'role:lgu_encoder'])->group(function () {
    // Weekly price references: draft, update, submit.
    Route::prefix('price-references')->group(function () {
        Route::get('/', [PriceReferenceController::class, 'index']);
        Route::post('/', [PriceReferenceController::class, 'store']);
        Route::get('{priceReference}', [PriceReferenceController::class, 'show']);
        Route::patch('{priceReference}', [PriceReferenceController::class, 'update']);
        Route::post('{priceReference}/submit', [PriceReferenceController::class, 'submit']);
    });

    // Livestock and derived calculations.
    Route::get('livestock', [LguLivestockController::class, 'index']);
    Route::post('farmers/{farmer}/livestock', [LguLivestockController::class, 'store']);
    Route::get('livestock/{livestock}', [LguLivestockController::class, 'show']);
    Route::patch('livestock/{livestock}', [LguLivestockController::class, 'update']);
    Route::get('livestock/{livestock}/weight-estimations', [LguWeightEstimationController::class, 'index']);
    Route::post('livestock/{livestock}/weight-estimations', [LguWeightEstimationController::class, 'store']);
    Route::get('livestock/{livestock}/valuations', [LguValuationController::class, 'index']);
    Route::post('livestock/{livestock}/valuations', [LguValuationController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| 09. Farmer
|--------------------------------------------------------------------------
| Farmer-owned profile, verification, livestock, and transaction actions.
*/
Route::prefix('farmer')->middleware(['auth:sanctum', 'role:farmer'])->group(function () {
    // Profile.
    Route::get('profile', [FarmerProfileController::class, 'show']);
    Route::put('profile', [FarmerProfileController::class, 'upsert']);

    // Identity verification documents.
    Route::get('verification-documents', [UserVerificationController::class, 'myDocuments']);
    Route::post('verification-documents', [UserVerificationController::class, 'upload']);
    Route::get('verification-documents/{document}', [UserVerificationController::class, 'showMyDocument']);
    Route::get('verification-documents/{document}/file', [UserVerificationController::class, 'myDocumentFile']);

    // Livestock, weight estimation, and valuation.
    Route::get('livestock', [FarmerLivestockController::class, 'index']);
    Route::post('livestock', [FarmerLivestockController::class, 'store']);
    Route::get('livestock/{livestock}', [FarmerLivestockController::class, 'show']);
    Route::patch('livestock/{livestock}', [FarmerLivestockController::class, 'update']);
    Route::patch('livestock/{livestock}/status', [FarmerLivestockController::class, 'updateStatus']);
    Route::get('livestock/{livestock}/weight-estimations', [FarmerWeightEstimationController::class, 'index']);
    Route::post('livestock/{livestock}/weight-estimations', [FarmerWeightEstimationController::class, 'store']);
    Route::get('livestock/{livestock}/valuations', [FarmerValuationController::class, 'index']);
    Route::post('livestock/{livestock}/valuations', [FarmerValuationController::class, 'store']);

    // Transaction submission and farmer cancellation.
    Route::post('transactions', [FarmerTransactionController::class, 'store']);
    Route::post('transactions/{transaction}/submit', [FarmerTransactionController::class, 'submit']);
    Route::post('transactions/{transaction}/cancel', [TransactionCancellationController::class, 'farmerCancel']);
});

/*
|--------------------------------------------------------------------------
| 10. Shared authenticated operational workflows
|--------------------------------------------------------------------------
| Preserve current middleware while reviewing controller-level authorization.
| Authentication alone is NOT sufficient authorization for LGU actions.
*/
Route::middleware('auth:sanctum')->group(function () {
    // LGU transaction verification and cancellation.
    Route::prefix('lgu/transactions')->group(function () {
        Route::post('{transaction}/approve', [LguTransactionVerificationController::class, 'approve']);
        Route::post('{transaction}/reject', [LguTransactionVerificationController::class, 'reject']);
        Route::post('{transaction}/cancel', [TransactionCancellationController::class, 'lguCancel']);
    });

    // Auction-house transaction submission.
    Route::post('auction-house/transactions', [AuctionHouseTransactionController::class, 'store']);

    // LGU auction-house administration and encoder assignment.
    Route::prefix('lgu/auction-houses')->group(function () {
        Route::get('/', [LguAuctionHouseController::class, 'index']);
        Route::post('/', [LguAuctionHouseController::class, 'store']);
        Route::patch('{auctionHouse}', [LguAuctionHouseController::class, 'update']);
        Route::patch('{auctionHouse}/status', [LguAuctionHouseController::class, 'status']);
        Route::post('{auctionHouse}/encoders', [LguAuctionHouseController::class, 'assignEncoder']);
        Route::delete('{auctionHouse}/encoders/{user}', [LguAuctionHouseController::class, 'removeEncoder']);
    });

    // LGU buyer-dispute investigation and resolution.
    Route::prefix('lgu/transaction-disputes')->group(function () {
        Route::get('/', [TransactionDisputeController::class, 'index']);
        Route::get('{dispute}', [TransactionDisputeController::class, 'show']);
        Route::post('{dispute}/review', [TransactionDisputeController::class, 'review']);
        Route::post('{dispute}/resolve', [TransactionDisputeController::class, 'resolve']);
    });
});

Route::prefix('analytics')
    ->middleware([
        'auth:sanctum',
        'role:admin,lgu_authority,lgu_encoder',
    ])
    ->group(function () {

        Route::get('dashboard', [DashboardController::class, 'overview']);
        Route::get('livestock', [DashboardController::class, 'livestock']);
        Route::get('prices', [PriceAnalyticsController::class, 'index']);
        Route::get('transactions', [DashboardController::class, 'transactions']);
        Route::get('valuations', [DashboardController::class, 'valuations']);
        Route::get('forecast', ForecastController::class);
        Route::get('reports/export', ReportController::class);
    });

Route::get('audit-logs', [AuditLogController::class, 'index'])
    ->middleware(['auth:sanctum', 'role:admin,lgu_authority']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/otp/send', [OtpController::class, 'send'])->middleware('throttle:5,1');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:10,1');
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
});