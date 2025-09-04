<?php

namespace App\Helpers;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Text\PendingRequest;

class ExpenseExtractor
{
    public function __construct(protected string $input)
    {
        //
    }

    public function extract(): array
    {
        $res = $this->intiateGemini()
            ->withSystemPrompt($this->setSystemPrompt())
            ->withPrompt($this->input)
            ->asText();

        return $this->cleanJsonResponse($res->text);
    }
    public function setSystemPrompt(): string
    {
        return <<<PROMPT
            You are a data extraction assistant.

            From any user text describing one or more purchases or expenses, extract and return ONLY valid JSON.

            ### Rules:
            - If the prompt describes **one item**, return a single JSON object.
            - If the prompt describes **multiple items**, return a JSON array of objects.

            ### Each object must have the following fields:
            {
            "title": string,         // short description of the item
            "quantity": number,      // numeric quantity (decimal if needed)
            "unit_price": number,    // per-unit price (decimal)
            "unit": string,          // unit of measurement (e.g., "kg", "litre", "ml", "pcs", "unit")
            "total_price": number,   // total cost (decimal)
            "category": string       // intelligent category ("Groceries", "Transport", "Travel", "Entertainment", etc.)
            }

            ### Additional rules:
            - If unit_price is missing, calculate it as (total_price / quantity).
            - If unit is missing, default to `"unit"`.
            - Use sensible categories based on the item.
            - All numeric values must be returned as numbers (not strings).
            - Always return valid JSON with no extra text.
PROMPT;
    }


    public function intiateGemini(): PendingRequest
    {
        return Prism::text()
            ->using(Provider::Gemini, 'gemini-2.0-flash');
    }

    /**
     * Cleans and decodes the model response to valid JSON.
     *
     * @param string $responseText
     * @return array<string, mixed>
     */
    public function cleanJsonResponse(string $responseText): array
    {
        // Remove code block markers and language hints
        $filtered = preg_replace('/^(```json|```|"""|json\\\\n)/m', '', $responseText);
        // Remove any trailing or leading whitespace
        $filtered = trim($filtered);
        // Remove escaped newlines
        $filtered = str_replace(['\\n', '\n'], '', $filtered);

        // Decode JSON
        return json_decode($filtered, true) ?? [];
    }
}
