<?php

namespace Codewiser\Otp\Http\Responses;

use Codewiser\Otp\Contracts\CodeSentResponse;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;

class CodeSent implements CodeSentResponse
{
    use LoggerAwareTrait;

    /**
     * @param  string  $status
     * @param  string  $redirectTo  Route path to redirect to.
     */
    public function __construct(protected string $status, protected string $redirectTo)
    {
        //
    }

    public function toResponse($request): Response
    {
        $this->logger?->debug(class_basename(__CLASS__), [
            'request'    => $request->method().' '.$request->path(),
            'status'     => $this->status,
            'redirectTo' => $this->redirectTo,
            'input'      => $request->input(),
        ]);

        return $request->wantsJson()
            ? new JsonResponse(['message' => trans($this->status)], 200)
            : redirect()->to($this->redirectTo)->with('status', $this->status)->withInput($request->input());
    }
}