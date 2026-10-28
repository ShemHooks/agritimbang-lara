<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectVerificationRequest;
use App\Http\Requests\UploadVerificationDocumentRequest;
use App\Services\UserVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserVerificationController extends BaseController
{
    public function __construct(
        private readonly UserVerificationService $verificationService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Farmer Verification Documents
    |--------------------------------------------------------------------------
    | Farmers can submit and view only their own documents.
    */

    public function myDocuments(Request $request): JsonResponse
    {
        $documents = $this->verificationService
            ->getDocuments($request->user());

        return $this->success(
            $documents,
            'Verification documents retrieved successfully.'
        );
    }

    public function upload(
        UploadVerificationDocumentRequest $request
    ): JsonResponse {
        $document = $this->verificationService
            ->uploadDocument(
                $request->user(),
                $request->validated(),
                $request->file('document')
            );

        return $this->success(
            $document,
            'Verification document submitted successfully.',
            201
        );
    }

    public function showMyDocument(
        Request $request,
        string $document
    ): JsonResponse {
        $document = $this->verificationService
            ->getFarmerDocument(
                $request->user(),
                $document
            );

        return $this->success(
            $document,
            'Verification document retrieved successfully.'
        );
    }

    public function myDocumentFile(
        Request $request,
        string $document
    ): StreamedResponse {
        $document = $this->verificationService
            ->getFarmerDocument(
                $request->user(),
                $document
            );

        return $this->streamPrivateDocument(
            $document->file_path
        );
    }

    /*
    |--------------------------------------------------------------------------
    | System Admin — Farmer Verification
    |--------------------------------------------------------------------------
    | Only System Admin can review, approve, or reject
    | farmer identity verification documents.
    */

    public function pending(Request $request): JsonResponse
    {
        $farmers = $this->verificationService
            ->getPendingFarmers($request->user());

        return $this->success(
            $farmers,
            'Pending farmer verifications retrieved successfully.'
        );
    }

    public function review(
        Request $request,
        string $farmer
    ): JsonResponse {
        $farmer = $this->verificationService
            ->getFarmerForReview(
                $request->user(),
                $farmer
            );

        return $this->success(
            $farmer,
            'Farmer verification details retrieved successfully.'
        );
    }

    public function reviewDocumentFile(
        Request $request,
        string $document
    ): StreamedResponse {
        $document = $this->verificationService
            ->getDocumentForReview(
                $request->user(),
                $document
            );

        return $this->streamPrivateDocument(
            $document->file_path
        );
    }

    public function approve(
        Request $request,
        string $document
    ): JsonResponse {
        $document = $this->verificationService
            ->approve(
                $request->user(),
                $document
            );

        return $this->success(
            $document,
            'Farmer verification approved successfully.'
        );
    }

    public function reject(
        RejectVerificationRequest $request,
        string $document
    ): JsonResponse {
        $document = $this->verificationService
            ->reject(
                $request->user(),
                $document,
                $request->validated()['rejection_reason']
            );

        return $this->success(
            $document,
            'Farmer verification rejected successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Private File Streaming
    |--------------------------------------------------------------------------
    */

    private function streamPrivateDocument(
        string $filePath
    ): StreamedResponse {
        $disk = Storage::disk('private');

        abort_unless(
            $disk->exists($filePath),
            404,
            'Verification document file not found.'
        );

        return $disk->response($filePath);
    }
}
