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

                    From any user text describing one or more purchases or expenses, extract and return **ONLY valid JSON**.

                    ---

                    ### Rules:

                    * If the prompt describes **one item**, return a single JSON object.
                    * If the prompt describes **multiple items**, return a JSON array of objects.
                    * If the prompt does **not** describe a valid purchase/expense (e.g., gibberish like `"test, argggs"`), return:

                    ```json
                    {
                        "message": "The prompt is invalid.",
                        "errors": {
                        "prompt": [
                            "Please provide a valid expense with quantity, price, or recognizable item details"
                        ]
                        }
                    }
                    ```

                    ---

                    ### Each object must have the following fields:

                    ```json
                    {
                    "title": string,        // short description of the item
                    "quantity": number,     // numeric quantity (decimal if needed)
                    "unit_price": number,   // per-unit price (decimal)
                    "unit": string,         // unit of measurement (e.g., "kg", "litre", "ml", "pcs", "unit")
                    "total_price": number,  // total cost (decimal)
                    "currency": string,     // detected or default currency ("₹", "$", "€", "GBP", etc.)
                    "category": string      // intelligent category ("Groceries", "Transport", "Travel", "Entertainment", etc.)
                    }
                    ```

                    ---

                    ### Additional rules:

                    * If `unit_price` is missing, calculate it as `(total_price / quantity)`.
                    * If the prompt says `"@ price"` or `"for price"`, treat that value as **total price** (not unit price).
                    * If `unit` is missing, default to `"unit"`.
                    * If `currency` is **not explicitly provided**, default to the **system’s locale currency** (e.g., `"₹"` for India, `"$"` for US).
                    * Use **sensible categories** based on the item.
                    * All numeric values (`quantity`, `unit_price`, `total_price`) must be **numbers**, not strings.
                    * Always return **valid JSON only**, with no extra text.

                    ---

                    ### Natural language quantity handling:

                    * `"half kilo"` → `0.5` with `"unit": "kg"`
                    * `"quarter kilo"` → `0.25` with `"unit": "kg"`
                    * `"one and a half kg"` → `1.5` with `"unit": "kg"`
                    * `"dozen"` → `12` with `"unit": "pcs"`
                    * `"half dozen"` → `6` with `"unit": "pcs"`
                    * `"pair"` → `2` with `"unit": "pcs"`
                    * `"dozen eggs"` → `12` with `"unit": "pcs"`
                    * `"litre"` / `"ml"` → numeric volume with correct unit
                    * `"pack"`, `"bottle"`, `"can"`, `"box"` → treated as `"unit": "unit"` unless a clearer measure is given

                    ---

                    ✅ Example:
                    **Prompt:** `"chicken 0.5 kg I have purchased @ 150 rs that means 1 kg price is 300"`

                    **Output:**

                    ```json
                    {
                    "title": "chicken",
                    "quantity": 0.5,
                    "unit_price": 300,
                    "unit": "kg",
                    "total_price": 150,
                    "currency": "₹",
                    "category": "Groceries"
                    }```
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
