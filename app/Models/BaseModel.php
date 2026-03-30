<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Models;

use MediaFeedbackV2\Core\Database;
use PDO;

abstract class BaseModel
{
    public function __construct(
        protected readonly Database $database
    ) {
    }

    protected function pdo(): PDO
    {
        return $this->database->connect();
    }
}