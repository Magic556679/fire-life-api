<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;

class CartException extends Exception
{
    protected int $status;
    protected array $errors;

    public function __construct(?string $message = null, int $status = 400, array $errors = [])
    {
        parent::__construct($message ?? 'Cart error occurred');
        $this->status = $status;
        $this->errors = $errors;
    }

    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], $this->status);
    }

    public function validationException(array $validationErrors = [])
    {
        throw ValidationException::withMessages($validationErrors ?: [
            'cart' => [$this->getMessage()],
        ]);
    }
}
