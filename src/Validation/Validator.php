<?php

declare(strict_types=1);

namespace Framework\Validation;

use InvalidArgumentException;

/**
 * Rule-based input validation.
 *
 *     $v = Validator::make($input, [
 *         'title'  => 'required|string|max:120',
 *         'status' => 'required|in:todo,doing,done',
 *     ]);
 *
 * Fields that are absent or empty are only checked by `required`; every
 * other rule is skipped for them.
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    private bool $ran = false;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     */
    public function __construct(private array $data, private array $rules)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function passes(): bool
    {
        $this->run();

        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    /**
     * Only the fields that have rules, trimmed when they are strings.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $this->run();

        return $this->validated;
    }

    private function run(): void
    {
        if ($this->ran) {
            return;
        }
        $this->ran = true;

        foreach ($this->rules as $field => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : $rules;
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }

            $isEmpty = $value === null || $value === '' || $value === [];
            $numeric = in_array('integer', $rules, true) || in_array('numeric', $rules, true);

            if ($isEmpty) {
                if (in_array('required', $rules, true)) {
                    $this->addError($field, "The $field field is required.");
                }
                if (array_key_exists($field, $this->data)) {
                    $this->validated[$field] = null;
                }
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                $message = $this->check($field, $value, $name, $parameter, $numeric);
                if ($message !== null) {
                    $this->addError($field, $message);
                    // One failure per field is enough for a form.
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function check(string $field, mixed $value, string $rule, ?string $parameter, bool $numeric): ?string
    {
        return match ($rule) {
            'required' => null,
            'string' => is_string($value) ? null : "The $field field must be text.",
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "The $field field must be a whole number.",
            'numeric' => is_numeric($value) ? null : "The $field field must be a number.",
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : "The $field field must be a valid email address.",
            'min' => $this->size($value, $numeric) >= (float) $parameter
                ? null
                : ($numeric ? "The $field field must be at least $parameter." : "The $field field must be at least $parameter characters."),
            'max' => $this->size($value, $numeric) <= (float) $parameter
                ? null
                : ($numeric ? "The $field field must not be greater than $parameter." : "The $field field must not exceed $parameter characters."),
            'in' => is_scalar($value) && in_array((string) $value, explode(',', (string) $parameter), true)
                ? null
                : "The selected $field is invalid.",
            default => throw new InvalidArgumentException("Unknown validation rule [$rule]."),
        };
    }

    private function size(mixed $value, bool $numeric): float
    {
        if ($numeric && is_numeric($value)) {
            return (float) $value;
        }
        if (is_array($value)) {
            return count($value);
        }

        return mb_strlen((string) $value);
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
