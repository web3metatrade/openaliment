<?php
declare(strict_types=1);
namespace App\Core;
final class Problem extends \RuntimeException {
    public function __construct(public int $status, public string $errorCode, string $message) { parent::__construct($message); }
}
