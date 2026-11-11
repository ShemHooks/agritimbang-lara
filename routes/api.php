<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\{
    AdminUserController,
    AuctionHouseTransactionController,
    AuditLogController,
    AuthController,
    BarangayController,
    BarangayGeometryController,
    BreedController,
    BuyerConfirmationController,
    DashboardController,
    FarmerDirectoryController,
    FarmerLivestockController,
    FarmerProfileController,
    FarmerTransactionController,
    FarmerValuationController,
    FarmerWeightEstimationController,
    ForecastController,
    GisController,
    LguAuctionHouseController,
    LguAuthorityController,
    LguEncoderController,
    LguGisMonitoringController,
    LguLivestockController,
    LguTransactionVerificationController,
    LguValuationController,
    LguWeightEstimationController,
    MunicipalityController,
    NotificationController,
    OfficialPriceController,
    OtpController,
    PasswordResetController,
    PriceAnalyticsController,
    PriceReferenceController,
    PriceValidationController,
    ReportController,
    SpeciesController,
    TransactionCancellationController,
    TransactionDisputeController,
    UserVerificationController,
    ProfileController,
};

/*
|--------------------------------------------------------------------------
| 01. PUBLIC AUTHENTICATION
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register/farmer', [
        AuthController::class,
        'registerFarmer'
    ]);

    Route::post('login', [
        AuthController::class,
        'login'
    ]);

    Route::post('forgot-password', [
        PasswordResetController::class,
        'forgot'
    ])->middleware('throttle:3,1');

    Route::post('reset-password', [
        PasswordResetController::class,
        'reset'
    ])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [
            AuthController::class,
            'me'
        ]);

        Route::post('logout', [
            AuthController::class,
            'logout'
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| 02. PUBLIC REFERENCE DATA
|--------------------------------------------------------------------------
*/

Route::prefix('reference')->group(function () {
    Route::get('municipalities', [
        MunicipalityController::class,
        'referenceIndex'
    ]);

    Route::get('municipalities/{municipality}/barangays', [
        BarangayController::class,
        'referenceByMunicipality'
    ]);

    Route::get('species', [
        SpeciesController::class,
        'referenceIndex'
    ]);

    Route::get('species/{species}/breeds', [
        BreedController::class,
        'referenceBySpecies'
    ]);
});

/*
|--------------------------------------------------------------------------
| 03. PUBLIC BUYER CONFIRMATION
|--------------------------------------------------------------------------
*/

Route::prefix('buyer-confirmations')
    ->middleware('throttle:10,1')
    ->group(function () {
        Route::get('{token}', [
            BuyerConfirmationController::class,
            'show'
        ]);

        Route::post('{token}/confirm', [
            BuyerConfirmationController::class,
            'confirm'
        ]);

        Route::post('{token}/dispute', [
            BuyerConfirmationController::class,
            'dispute'
        ]);
    });

/*
|--------------------------------------------------------------------------
| 04. GIS REFERENCE AND LIVESTOCK MONITORING
|--------------------------------------------------------------------------
*/

Route::prefix('gis')->group(function () {
    Route::get('municipalities', [
        GisController::class,
        'municipalities'
    ]);

    Route::get('barangays', [
        GisController::class,
        'barangays'
    ]);

    Route::get('geojson', [
        GisController::class,
        'geojson'
    ]);

    Route::middleware([
        'auth:sanctum',
        'role:admin,lgu_authority,lgu_encoder'
    ])->group(function () {
        Route::get('monitoring-geojson', [
            GisController::class,
            'monitoringGeojson'
        ]);

        Route::get('livestock-summary', [
            GisController::class,
            'livestockSummary'
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| 05. SYSTEM ADMINISTRATOR
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware([
        'auth:sanctum',
        'role:admin'
    ])
    ->group(function () {

        // Municipality management
    
        Route::patch('municipalities/{municipality}/activate', [
            MunicipalityController::class,
            'activate'
        ]);

        Route::patch('municipalities/{municipality}/deactivate', [
            MunicipalityController::class,
            'destroy'
        ]);

        Route::apiResource(
            'municipalities',
            MunicipalityController::class
        );

        // LGU Authority account management
    
        Route::prefix('lgu-authorities')->group(function () {
            Route::get('/', [
                LguAuthorityController::class,
                'index'
            ]);

            Route::post('/', [
                LguAuthorityController::class,
                'store'
            ]);

            Route::get('{authority}', [
                LguAuthorityController::class,
                'show'
            ]);

            Route::patch('{authority}', [
                LguAuthorityController::class,
                'update'
            ]);

            Route::patch('{authority}/status', [
                LguAuthorityController::class,
                'updateStatus'
            ]);
        });

        // Farmer verification — all municipalities
    
        Route::prefix('farmer-verifications')
            ->group(function () {
                Route::get('/', [
                    UserVerificationController::class,
                    'pending'
                ]);

                Route::get('{farmer}', [
                    UserVerificationController::class,
                    'review'
                ]);
            });

        Route::prefix('verification-documents')
            ->group(function () {
                Route::get('{document}/file', [
                    UserVerificationController::class,
                    'reviewDocumentFile'
                ]);

                Route::post('{document}/approve', [
                    UserVerificationController::class,
                    'approve'
                ]);

                Route::post('{document}/reject', [
                    UserVerificationController::class,
                    'reject'
                ]);
            });

        // Centralized user management
    
        Route::prefix('users')->group(function () {
            Route::get('summary', [
                AdminUserController::class,
                'summary'
            ]);

            Route::get('/', [
                AdminUserController::class,
                'index'
            ]);

            Route::get('{user}', [
                AdminUserController::class,
                'show'
            ]);

            Route::patch('{user}/status', [
                AdminUserController::class,
                'updateStatus'
            ]);
        });

        // Barangay and GIS geometry management
    
        Route::get('municipalities/{municipality}/barangays', [
            BarangayController::class,
            'indexByMunicipality'
        ]);

        Route::prefix('barangays')->group(function () {
            Route::get('/', [
                BarangayController::class,
                'index'
            ]);

            Route::post('/', [
                BarangayController::class,
                'store'
            ]);

            Route::get('{barangay}', [
                BarangayController::class,
                'show'
            ]);

            Route::patch('{barangay}', [
                BarangayController::class,
                'update'
            ]);

            Route::patch('{barangay}/status', [
                BarangayController::class,
                'updateStatus'
            ]);

            Route::get('{barangay}/geometry', [
                BarangayGeometryController::class,
                'show'
            ]);

            Route::put('{barangay}/geometry', [
                BarangayGeometryController::class,
                'upsert'
            ]);
        });

        // Species management
    
        Route::prefix('species')->group(function () {
            Route::get('/', [
                SpeciesController::class,
                'index'
            ]);

            Route::post('/', [
                SpeciesController::class,
                'store'
            ]);

            Route::get('{species}', [
                SpeciesController::class,
                'show'
            ]);

            Route::patch('{species}', [
                SpeciesController::class,
                'update'
            ]);

            Route::patch('{species}/status', [
                SpeciesController::class,
                'updateStatus'
            ]);
        });

        // Breed management
    
        Route::prefix('breeds')->group(function () {
            Route::get('/', [
                BreedController::class,
                'index'
            ]);

            Route::post('/', [
                BreedController::class,
                'store'
            ]);

            Route::get('{breed}', [
                BreedController::class,
                'show'
            ]);

            Route::patch('{breed}', [
                BreedController::class,
                'update'
            ]);

            Route::patch('{breed}/status', [
                BreedController::class,
                'updateStatus'
            ]);
        });
    });

/*
|--------------------------------------------------------------------------
| 06. LGU AUTHORITY
|--------------------------------------------------------------------------
| All operational changes remain municipality-scoped.
| GIS monitoring is read-only across municipalities.
|--------------------------------------------------------------------------
*/

Route::prefix('lgu')
    ->middleware([
        'auth:sanctum',
        'role:lgu_authority'
    ])
    ->group(function () {

        // LGU Encoder account management
    
        Route::prefix('encoders')->group(function () {
            Route::get('/', [
                LguEncoderController::class,
                'index'
            ]);

            Route::post('/', [
                LguEncoderController::class,
                'store'
            ]);

            Route::get('{encoder}', [
                LguEncoderController::class,
                'show'
            ]);

            Route::patch('{encoder}', [
                LguEncoderController::class,
                'update'
            ]);

            Route::patch('{encoder}/status', [
                LguEncoderController::class,
                'updateStatus'
            ]);
        });

        // Farmer verification — assigned municipality only
    
        Route::prefix('farmer-verifications')
            ->group(function () {
                Route::get('/', [
                    UserVerificationController::class,
                    'pending'
                ]);

                Route::get('{farmer}', [
                    UserVerificationController::class,
                    'review'
                ]);
            });

        Route::prefix('verification-documents')
            ->group(function () {
                Route::get('{document}/file', [
                    UserVerificationController::class,
                    'reviewDocumentFile'
                ]);

                Route::post('{document}/approve', [
                    UserVerificationController::class,
                    'approve'
                ]);

                Route::post('{document}/reject', [
                    UserVerificationController::class,
                    'reject'
                ]);
            });

        // Official price validation
    
        Route::prefix('price-validations')
            ->group(function () {
                Route::get('/', [
                    PriceValidationController::class,
                    'index'
                ]);

                Route::get('{priceReference}', [
                    PriceValidationController::class,
                    'show'
                ]);

                Route::post('{priceReference}/approve', [
                    PriceValidationController::class,
                    'approve'
                ]);

                Route::post('{priceReference}/reject', [
                    PriceValidationController::class,
                    'reject'
                ]);
            });

        // Cross-municipality GIS monitoring — read-only
    
        Route::prefix('gis-monitoring')
            ->group(function () {
                Route::get('geojson', [
                    LguGisMonitoringController::class,
                    'geojson'
                ]);

                Route::get('summary', [
                    LguGisMonitoringController::class,
                    'summary'
                ]);

                Route::get('prices', [
                    LguGisMonitoringController::class,
                    'prices'
                ]);

                Route::get('barangays/{barangay}/prices', [
                    LguGisMonitoringController::class,
                    'barangayPrices'
                ]);
            });
    });

/*
|--------------------------------------------------------------------------
| 07. LGU ENCODER
|--------------------------------------------------------------------------
*/

Route::prefix('lgu')
    ->middleware([
        'auth:sanctum',
        'role:lgu_encoder'
    ])
    ->group(function () {

        // Weekly price references
    
        Route::prefix('price-references')
            ->group(function () {
                Route::get('/', [
                    PriceReferenceController::class,
                    'index'
                ]);

                Route::post('/', [
                    PriceReferenceController::class,
                    'store'
                ]);

                Route::get('{priceReference}', [
                    PriceReferenceController::class,
                    'show'
                ]);

                Route::patch('{priceReference}', [
                    PriceReferenceController::class,
                    'update'
                ]);

                Route::post('{priceReference}/submit', [
                    PriceReferenceController::class,
                    'submit'
                ]);
            });

        // Livestock registration
    
        Route::post('farmers/{farmer}/livestock', [
            LguLivestockController::class,
            'store'
        ]);

        Route::get('livestock/{livestock}', [
            LguLivestockController::class,
            'show'
        ]);

        Route::patch('livestock/{livestock}', [
            LguLivestockController::class,
            'update'
        ]);

        // Weight estimation
    
        Route::get('livestock/{livestock}/weight-estimations', [
            LguWeightEstimationController::class,
            'index'
        ]);

        Route::post('livestock/{livestock}/weight-estimations', [
            LguWeightEstimationController::class,
            'store'
        ]);

        // Valuation
    
        Route::get('livestock/{livestock}/valuations', [
            LguValuationController::class,
            'index'
        ]);

        Route::post('livestock/{livestock}/valuations', [
            LguValuationController::class,
            'store'
        ]);
    });

/*
|--------------------------------------------------------------------------
| 08. FARMER
|--------------------------------------------------------------------------
*/

Route::prefix('farmer')
    ->middleware([
        'auth:sanctum',
        'role:farmer'
    ])
    ->group(function () {

        // Farmer profile
    
        Route::get('profile', [
            FarmerProfileController::class,
            'show'
        ]);

        Route::put('profile', [
            FarmerProfileController::class,
            'upsert'
        ]);

        // Own verification documents
    
        Route::prefix('verification-documents')
            ->group(function () {
                Route::get('/', [
                    UserVerificationController::class,
                    'myDocuments'
                ]);

                Route::post('/', [
                    UserVerificationController::class,
                    'upload'
                ]);

                Route::get('{document}', [
                    UserVerificationController::class,
                    'showMyDocument'
                ]);

                Route::get('{document}/file', [
                    UserVerificationController::class,
                    'myDocumentFile'
                ]);
            });

        // Livestock
    
        Route::get('livestock', [
            FarmerLivestockController::class,
            'index'
        ]);

        Route::post('livestock', [
            FarmerLivestockController::class,
            'store'
        ]);

        Route::get('livestock/{livestock}', [
            FarmerLivestockController::class,
            'show'
        ]);

        Route::patch('livestock/{livestock}', [
            FarmerLivestockController::class,
            'update'
        ]);

        Route::patch('livestock/{livestock}/status', [
            FarmerLivestockController::class,
            'updateStatus'
        ]);

        // Weight estimation
    
        Route::get('livestock/{livestock}/weight-estimations', [
            FarmerWeightEstimationController::class,
            'index'
        ]);

        Route::post('livestock/{livestock}/weight-estimations', [
            FarmerWeightEstimationController::class,
            'store'
        ]);

        // Valuation
    
        Route::get('livestock/{livestock}/valuations', [
            FarmerValuationController::class,
            'index'
        ]);

        Route::post('livestock/{livestock}/valuations', [
            FarmerValuationController::class,
            'store'
        ]);

        // Transactions
    
        Route::post('transactions', [
            FarmerTransactionController::class,
            'store'
        ]);

        Route::post('transactions/{transaction}/submit', [
            FarmerTransactionController::class,
            'submit'
        ]);

        Route::post('transactions/{transaction}/cancel', [
            TransactionCancellationController::class,
            'farmerCancel'
        ]);
    });

/*
|--------------------------------------------------------------------------
| 09. SHARED AUTHENTICATED WORKFLOWS
|--------------------------------------------------------------------------
| Existing controller/service authorization remains responsible
| for operation-specific permissions and municipality ownership.
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // LGU transaction operations

    Route::prefix('lgu/transactions')
        ->group(function () {
            Route::post('{transaction}/approve', [
                LguTransactionVerificationController::class,
                'approve'
            ]);

            Route::post('{transaction}/reject', [
                LguTransactionVerificationController::class,
                'reject'
            ]);

            Route::post('{transaction}/cancel', [
                TransactionCancellationController::class,
                'lguCancel'
            ]);
        });

    // Auction-house transactions

    Route::post('auction-house/transactions', [
        AuctionHouseTransactionController::class,
        'store'
    ]);

    // LGU auction-house administration

    Route::prefix('lgu/auction-houses')
        ->group(function () {
            Route::get('/', [
                LguAuctionHouseController::class,
                'index'
            ]);

            Route::post('/', [
                LguAuctionHouseController::class,
                'store'
            ]);

            Route::patch('{auctionHouse}', [
                LguAuctionHouseController::class,
                'update'
            ]);

            Route::patch('{auctionHouse}/status', [
                LguAuctionHouseController::class,
                'status'
            ]);

            Route::post('{auctionHouse}/encoders', [
                LguAuctionHouseController::class,
                'assignEncoder'
            ]);

            Route::delete('{auctionHouse}/encoders/{user}', [
                LguAuctionHouseController::class,
                'removeEncoder'
            ]);

            Route::get('eligible-encoders', [
                LguAuctionHouseController::class,
                'eligibleEncoders'
            ]);

            Route::get('{auctionHouse}/encoders', [
                LguAuctionHouseController::class,
                'assignedEncoders'
            ]);
        });

    // LGU transaction disputes

    Route::prefix('lgu/transaction-disputes')
        ->group(function () {
            Route::get('/', [
                TransactionDisputeController::class,
                'index'
            ]);

            Route::get('{dispute}', [
                TransactionDisputeController::class,
                'show'
            ]);

            Route::post('{dispute}/review', [
                TransactionDisputeController::class,
                'review'
            ]);

            Route::post('{dispute}/resolve', [
                TransactionDisputeController::class,
                'resolve'
            ]);
        });
});

/*
|--------------------------------------------------------------------------
| 10. ANALYTICS AND REPORTING
|--------------------------------------------------------------------------
*/

Route::prefix('analytics')
    ->middleware([
        'auth:sanctum',
        'role:admin,lgu_authority,lgu_encoder'
    ])
    ->group(function () {
        Route::get('dashboard', [
            DashboardController::class,
            'overview'
        ]);

        Route::get('livestock', [
            DashboardController::class,
            'livestock'
        ]);

        Route::get('prices', [
            PriceAnalyticsController::class,
            'index'
        ]);

        Route::get('transactions', [
            DashboardController::class,
            'transactions'
        ]);

        Route::get('valuations', [
            DashboardController::class,
            'valuations'
        ]);

        Route::get('forecast', [
            ForecastController::class
        ]);

        Route::get('reports/export', [
            ReportController::class
        ]);
    });

/*
|--------------------------------------------------------------------------
| 11. AUDIT LOGS
|--------------------------------------------------------------------------
*/

Route::get('audit-logs', [
    AuditLogController::class,
    'index'
])->middleware([
            'auth:sanctum',
            'role:admin,lgu_authority'
        ]);

/*
|--------------------------------------------------------------------------
| 12. NOTIFICATIONS AND OTP
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::post('otp/send', [
        OtpController::class,
        'send'
    ])->middleware('throttle:5,1');

    Route::post('otp/verify', [
        OtpController::class,
        'verify'
    ])->middleware('throttle:10,1');

    Route::get('notifications', [
        NotificationController::class,
        'index'
    ]);

    Route::get('notifications/unread-count', [
        NotificationController::class,
        'unreadCount'
    ]);

    Route::patch('notifications/read-all', [
        NotificationController::class,
        'markAllRead'
    ]);

    Route::patch('notifications/{id}/read', [
        NotificationController::class,
        'markRead'
    ]);
});

/*
|--------------------------------------------------------------------------
| 13. FARMER DIRECTORY
|--------------------------------------------------------------------------
*/

Route::prefix('farmers')
    ->middleware([
        'auth:sanctum',
        'role:admin,lgu_authority,lgu_encoder'
    ])
    ->group(function () {
        Route::get('/', [
            FarmerDirectoryController::class,
            'index'
        ]);

        Route::get('{farmer}', [
            FarmerDirectoryController::class,
            'show'
        ]);
    });

/*
|--------------------------------------------------------------------------
| 14. LIVESTOCK DIRECTORY
|--------------------------------------------------------------------------
*/

Route::get('lgu/livestock', [
    LguLivestockController::class,
    'index'
])->middleware([
            'auth:sanctum',
            'role:admin,lgu_authority,lgu_encoder'
        ]);

/*
|--------------------------------------------------------------------------
| 15. OFFICIAL LIVESTOCK PRICES
|--------------------------------------------------------------------------
*/

Route::get('official-prices', [
    OfficialPriceController::class,
    'index'
])->middleware([
            'auth:sanctum',
            'role:farmer,lgu_encoder,lgu_authority'
        ]);



/*
|--------------------------------------------------------------------------
| PROFILE SETTINGS
|--------------------------------------------------------------------------
| Shared by Admin, LGU Authority, LGU Encoder, and Farmer.
|--------------------------------------------------------------------------
*/

Route::prefix('profile')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [
            ProfileController::class,
            'show'
        ]);

        Route::patch('/', [
            ProfileController::class,
            'update'
        ]);

        Route::put('password', [
            ProfileController::class,
            'changePassword'
        ])->middleware('throttle:5,1');
    });
