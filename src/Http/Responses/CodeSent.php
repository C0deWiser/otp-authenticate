<?php

namespace Codewiser\Otp\Http\Responses;

use Codewiser\Otp\Contracts\SendRequestResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CodeSent implements SendRequestResponse
{
    /**
     * @param  string  $status
     * @param  string  $redirectTo Route path to redirect to.
     */
    public function __construct(protected string $status, protected string $redirectTo)
    {
        //
    }

    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => trans($this->status)], 200)
            : redirect()->to($this->redirectTo)->with('status', trans($this->status));
    }
}