<?php declare(strict_types=1);

namespace Lturi\ApiFramework\Exception;

use Exception;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Traversable;

class EntityValidationException extends Exception {
    public function __construct (Traversable $messages, string $entityName)
    {
        $errors = [];
        /** @var ConstraintViolation $validationError */
        foreach ($messages as $validationError) {
            if ($validationError->getConstraint()->getTargets() === Constraint::CLASS_CONSTRAINT) {
                $errors[] = [
                    "path" => "$entityName.".$validationError->getPropertyPath(),
                    "message" => "CLASS: " . $validationError->getMessage()
                ];
            } else {
                $errors[] = [
                    "path" => "$entityName.".$validationError->getPropertyPath(),
                    "message" => $validationError->getMessage()
                ];
            }
        }
        parent::__construct(implode("\n", array_map(function ($error) {
            return $error["path"].":".$error["message"];
        }, $errors)));
    }
}