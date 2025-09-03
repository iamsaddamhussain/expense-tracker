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
            From any user text describing a purchase or expense, extract and return ONLY valid JSON with these fields:

            {
            "title": string,
            "quantity": number,
            "unit_price": number,
            "unit": string,
            "total_price": number,
            "category": string
            }

            Rules:
            - Always respond in pure JSON, no explanations.
            - Title should be a brief description of the item purchased.
            - If unit_price is missing, infer it as (total_price / quantity).
            - Unit can be litre/KG/ml/gram.
            - Categories should be determined intelligently from the item mentioned (e.g., "bananas" → "Groceries", "cab" → "Transport", "hotel" → "Travel", etc.).
            - Numbers should be returned as decimals, not strings.
            - Ensure JSON is always valid and contains all 4 fields.
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
