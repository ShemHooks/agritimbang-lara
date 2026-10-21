<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarangayController;
use App\Http\Controllers\BarangayGeometryController;
use App\Http\Controllers\BreedController;
use App\Http\Controllers\FarmerProfileController;
use App\Http\Controllers\LguAuthorityController;
use App\Http\Controllers\LguEncoderController;
use App\Http\Controllers\MunicipalityController;
use App\Http\Controllers\PriceReferenceController;
use App\Http\Controllers\SpeciesController;
use App\Http\Controllers\UserVerificationController;
use App\Http\Controllers\PriceValidationController;
use App\Http\Controllers\FarmerLivestockController;
use App\Http\Controllers\LguLivestockController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FarmerWeightEstimationController;
use App\Http\Controllers\LguWeightEstimationController;
use App\Http\Controllers\FarmerValuationController;
use App\Http\Controllers\LguValuationController;
use App\Http\Controllers\FarmerTransactionController;
use App\Http\Controllers\BuyerConfirmationController;
use App\Http\Controllers\LguTransactionVerificationController;
use App\Http\Controllers\AuctionHouseTransactionController;
use App\Http\Controllers\LguAuctionHouseController;
use App\Http\Controllers\TransactionDisputeController;
use App\Http\Controllers\TransactionCancellationController;
/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    Route::post('/register/farmer', [
        AuthController::class,
        'registerFarmer',
    ]);

    Route::post('/login', [
        AuthController::class,
        'login',
    ]);

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', [
            AuthController::class,
            'me',
        ]);

        Route::post('/logout', [
            AuthController::class,
            'logout',
        ]);
    });
});


/*
|--------------------------------------------------------------------------
| Reference Data
|--------------------------------------------------------------------------
|
| Read-only reference data used by forms and selection controls.
|
*/

Route::get(
    '/reference/municipalities',
    [MunicipalityController::class, 'referenceIndex']
);

Route::get(
    '/reference/municipalities/{municipality}/barangays',
    [BarangayController::class, 'referenceByMunicipality']
);

Route::get(
    '/reference/species',
    [SpeciesController::class, 'referenceIndex']
);

Route::get(
    '/reference/species/{species}/breeds',
    [BreedController::class, 'referenceBySpecies']
);


Route::prefix('buyer-confirmations')
    ->middleware('throttle:10,1')
    ->group(function () {
        Route::get(
            '{token}',
            [BuyerConfirmationController::class, 'show']
        );

        Route::post(
            '{token}/confirm',
            [BuyerConfirmationController::class, 'confirm']
        );

        Route::post(
            '{token}/dispute',
            [BuyerConfirmationController::class, 'dispute']
        );
    });


/*
|--------------------------------------------------------------------------
| System Administrator
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'role:admin',
])
    ->prefix('admin')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Municipalities
        |--------------------------------------------------------------------------
        */

        Route::apiResource(
            'municipalities',
            MunicipalityController::class
        );


        /*
        |--------------------------------------------------------------------------
        | LGU Authorities
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/lgu-authorities',
            [LguAuthorityController::class, 'index']
        );

        Route::post(
            '/lgu-authorities',
            [LguAuthorityController::class, 'store']
        );

        Route::get(
            '/lgu-authorities/{authority}',
            [LguAuthorityController::class, 'show']
        );

        Route::patch(
            '/lgu-authorities/{authority}',
            [LguAuthorityController::class, 'update']
        );

        Route::patch(
            '/lgu-authorities/{authority}/status',
            [LguAuthorityController::class, 'updateStatus']
        );


        /*
        |--------------------------------------------------------------------------
        | Barangays
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/barangays',
            [BarangayController::class, 'index']
        );

        Route::post(
            '/barangays',
            [BarangayController::class, 'store']
        );

        Route::get(
            '/barangays/{barangay}',
            [BarangayController::class, 'show']
        );

        Route::patch(
            '/barangays/{barangay}',
            [BarangayController::class, 'update']
        );

        Route::patch(
            '/barangays/{barangay}/status',
            [BarangayController::class, 'updateStatus']
        );


        /*
        |--------------------------------------------------------------------------
        | Barangays by Municipality
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/municipalities/{municipality}/barangays',
            [BarangayController::class, 'indexByMunicipality']
        );


        /*
        |--------------------------------------------------------------------------
        | Barangay GIS Geometry
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/barangays/{barangay}/geometry',
            [BarangayGeometryController::class, 'show']
        );

        Route::put(
            '/barangays/{barangay}/geometry',
            [BarangayGeometryController::class, 'upsert']
        );


        /*
        |--------------------------------------------------------------------------
        | Species Management
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/species',
            [SpeciesController::class, 'index']
        );

        Route::post(
            '/species',
            [SpeciesController::class, 'store']
        );

        Route::get(
            '/species/{species}',
            [SpeciesController::class, 'show']
        );

        Route::patch(
            '/species/{species}',
            [SpeciesController::class, 'update']
        );

        Route::patch(
            '/species/{species}/status',
            [SpeciesController::class, 'updateStatus']
        );


        /*
        |--------------------------------------------------------------------------
        | Breed Management
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/breeds',
            [BreedController::class, 'index']
        );

        Route::post(
            '/breeds',
            [BreedController::class, 'store']
        );

        Route::get(
            '/breeds/{breed}',
            [BreedController::class, 'show']
        );

        Route::patch(
            '/breeds/{breed}',
            [BreedController::class, 'update']
        );

        Route::patch(
            '/breeds/{breed}/status',
            [BreedController::class, 'updateStatus']
        );
    });


/*
|--------------------------------------------------------------------------
| LGU Authority
|--------------------------------------------------------------------------
|
| Municipality-level administrative operations.
|
*/

Route::middleware([
    'auth:sanctum',
    'role:lgu_authority',
])
    ->prefix('lgu')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | LGU Encoders
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/encoders',
            [LguEncoderController::class, 'index']
        );

        Route::post(
            '/encoders',
            [LguEncoderController::class, 'store']
        );

        Route::get(
            '/encoders/{encoder}',
            [LguEncoderController::class, 'show']
        );

        Route::patch(
            '/encoders/{encoder}',
            [LguEncoderController::class, 'update']
        );

        Route::patch(
            '/encoders/{encoder}/status',
            [LguEncoderController::class, 'updateStatus']
        );


        /*
 |--------------------------------------------------------------------------
 | Farmer Verification
 |--------------------------------------------------------------------------
 */

        Route::get(
            '/farmer-verifications',
            [UserVerificationController::class, 'pending']
        );

        Route::get(
            '/farmer-verifications/{farmer}',
            [UserVerificationController::class, 'review']
        );

        Route::get(
            '/verification-documents/{document}/file',
            [UserVerificationController::class, 'reviewDocumentFile']
        );

        Route::post(
            '/verification-documents/{document}/approve',
            [UserVerificationController::class, 'approve']
        );

        Route::post(
            '/verification-documents/{document}/reject',
            [UserVerificationController::class, 'reject']
        );


        /*
        |--------------------------------------------------------------------------
        | Price Validation
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/price-validations',
            [PriceValidationController::class, 'index']
        );

        Route::get(
            '/price-validations/{priceReference}',
            [PriceValidationController::class, 'show']
        );

        Route::post(
            '/price-validations/{priceReference}/approve',
            [PriceValidationController::class, 'approve']
        );

        Route::post(
            '/price-validations/{priceReference}/reject',
            [PriceValidationController::class, 'reject']
        );
    });


/*
|--------------------------------------------------------------------------
| LGU Encoder
|--------------------------------------------------------------------------
|
| Operational data-entry routes for LGU Encoders.
|
*/

Route::middleware([
    'auth:sanctum',
    'role:lgu_encoder',
])
    ->prefix('lgu')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Price Reference Management
        |--------------------------------------------------------------------------
        |
        | LGU Encoders create and maintain draft price references.
        | A draft must be submitted before an LGU Authority can review it.
        |
        */

        Route::get(
            '/price-references',
            [PriceReferenceController::class, 'index']
        );

        Route::post(
            '/price-references',
            [PriceReferenceController::class, 'store']
        );

        Route::get(
            '/price-references/{priceReference}',
            [PriceReferenceController::class, 'show']
        );

        Route::patch(
            '/price-references/{priceReference}',
            [PriceReferenceController::class, 'update']
        );

        Route::post(
            '/price-references/{priceReference}/submit',
            [PriceReferenceController::class, 'submit']
        );

        /*
|--------------------------------------------------------------------------
| Livestock Records
|--------------------------------------------------------------------------
*/

        Route::get(
            '/livestock',
            [LguLivestockController::class, 'index']
        );

        Route::post(
            '/farmers/{farmer}/livestock',
            [LguLivestockController::class, 'store']
        );

        Route::get(
            '/livestock/{livestock}',
            [LguLivestockController::class, 'show']
        );

        Route::patch(
            '/livestock/{livestock}',
            [LguLivestockController::class, 'update']
        );

        Route::prefix('livestock/{livestock}/weight-estimations')
            ->group(function () {
                Route::get(
                    '/',
                    [LguWeightEstimationController::class, 'index']
                );

                Route::post(
                    '/',
                    [LguWeightEstimationController::class, 'store']
                );
            });

        Route::prefix('livestock/{livestock}/valuations')
            ->group(function () {
                Route::get('/', [
                    LguValuationController::class,
                    'index'
                ]);

                Route::post('/', [
                    LguValuationController::class,
                    'store'
                ]);
            });
    });


/*
|--------------------------------------------------------------------------
| Farmer
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'role:farmer',
])
    ->prefix('farmer')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Farmer Profile
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/profile',
            [FarmerProfileController::class, 'show']
        );

        Route::put(
            '/profile',
            [FarmerProfileController::class, 'upsert']
        );


        /*
        |--------------------------------------------------------------------------
        | Verification Documents
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/verification-documents',
            [UserVerificationController::class, 'myDocuments']
        );

        Route::post(
            '/verification-documents',
            [UserVerificationController::class, 'upload']
        );

        Route::get(
            '/verification-documents/{document}',
            [UserVerificationController::class, 'showMyDocument']
        );

        Route::get(
            '/verification-documents/{document}/file',
            [UserVerificationController::class, 'myDocumentFile']
        );

        /*
|--------------------------------------------------------------------------
| Livestock Records
|--------------------------------------------------------------------------
*/

        Route::get(
            '/livestock',
            [FarmerLivestockController::class, 'index']
        );

        Route::post(
            '/livestock',
            [FarmerLivestockController::class, 'store']
        );

        Route::get(
            '/livestock/{livestock}',
            [FarmerLivestockController::class, 'show']
        );

        Route::patch(
            '/livestock/{livestock}',
            [FarmerLivestockController::class, 'update']
        );

        Route::patch(
            '/livestock/{livestock}/status',
            [FarmerLivestockController::class, 'updateStatus']
        );

        Route::prefix('livestock/{livestock}/weight-estimations')
            ->group(function () {
                Route::get(
                    '/',
                    [FarmerWeightEstimationController::class, 'index']
                );

                Route::post(
                    '/',
                    [FarmerWeightEstimationController::class, 'store']
                );
            });


        Route::prefix('livestock/{livestock}/valuations')
            ->group(function () {
                Route::get('/', [
                    FarmerValuationController::class,
                    'index'
                ]);

                Route::post('/', [
                    FarmerValuationController::class,
                    'store'
                ]);
            });

        Route::post(
            'transactions',
            [FarmerTransactionController::class, 'store']
        );

        Route::post(
            'transactions/{transaction}/submit',
            [FarmerTransactionController::class, 'submit']
        );

        Route::post(
            'transactions/{transaction}/cancel',
            [TransactionCancellationController::class, 'farmerCancel']
        );
    });


Route::middleware('auth:sanctum')
    ->prefix('lgu/transactions')
    ->group(function () {
        Route::post(
            '{transaction}/approve',
            [LguTransactionVerificationController::class, 'approve']
        );

        Route::post(
            '{transaction}/reject',
            [LguTransactionVerificationController::class, 'reject']
        );

        Route::post(
            '{transaction}/cancel',
            [TransactionCancellationController::class, 'lguCancel']
        );
    });

Route::middleware('auth:sanctum')
    ->prefix('auction-house')
    ->group(function () {
        Route::post(
            'transactions',
            [AuctionHouseTransactionController::class, 'store']
        );
    });


Route::middleware('auth:sanctum')
    ->prefix('lgu/auction-houses')
    ->group(function () {
        Route::get('/', [
            LguAuctionHouseController::class,
            'index'
        ]);

        Route::post('/', [
            LguAuctionHouseController::class,
            'store'
        ]);

        Route::patch('/{auctionHouse}', [
            LguAuctionHouseController::class,
            'update'
        ]);

        Route::patch('/{auctionHouse}/status', [
            LguAuctionHouseController::class,
            'status'
        ]);

        Route::post('/{auctionHouse}/encoders', [
            LguAuctionHouseController::class,
            'assignEncoder'
        ]);

        Route::delete('/{auctionHouse}/encoders/{user}', [
            LguAuctionHouseController::class,
            'removeEncoder'
        ]);
    });


Route::middleware('auth:sanctum')
    ->prefix('lgu/transaction-disputes')
    ->group(function () {
        Route::get('/', [
            TransactionDisputeController::class,
            'index',
        ]);

        Route::get('/{dispute}', [
            TransactionDisputeController::class,
            'show',
        ]);

        Route::post('/{dispute}/review', [
            TransactionDisputeController::class,
            'review',
        ]);

        Route::post('/{dispute}/resolve', [
            TransactionDisputeController::class,
            'resolve',
        ]);
    });
