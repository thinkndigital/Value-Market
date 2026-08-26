<?php

namespace App\Http\Controllers\Seller;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\SettingService;

class AiController extends Controller
{
    public function generateShortDescription(Request $request)
    {
        $productName = $request->input('product_name');
        $languageCode = $request->input('language_code', 'en');
        $customPrompt = $request->input('prompt');

        if (!$productName) {
            return response()->json(['message' => 'Product name is required.'], 422);
        }

        $prompts = [
            'af' => "Skryf 'n kort en aantreklike produkbeskrywing vir: {$productName}. Maak seker dat die beskrywing duidelik, volledig en sonder enige afbrekings is.",
            'am' => "የእትም ጥሩ እና ምርጥ ምሳሌ አቅርበው: {$productName}. እባኮትን የመረጃውን መግለጫ ግሩምና ሙሉ አድርጉ።",
            'ar' => "اكتب وصفًا قصيرًا وجذابًا لهذا المنتج: {$productName}. تأكد من أن الوصف واضح وكامل ولا يحتوي على أي كلمات مقطوعة.",
            'az' => "Bu məhsul üçün qısa və cazibədar bir təsvir yazın: {$productName}. Əmin olun ki, təsvir aydın, tamdır və heç bir söz yarıda kəsilməyib.",
            'be' => "Напішыце кароткае і прывабнае апісанне гэтага прадукту: {$productName}. Пераканайцеся, што апісанне яснае, поўнае і не абрываецца.",
            'bg' => "Напишете кратко и привлекателно описание на този продукт: {$productName}. Уверете се, че описанието е ясно, завършено и не съдържа прекъсвания.",
            'bn' => "এই পণ্যের জন্য একটি সংক্ষিপ্ত এবং আকর্ষণীয় বিবরণ লিখুন: {$productName}. নিশ্চিত করুন যে বিবরণটি পরিষ্কার, পূর্ণাঙ্গ এবং কোনো শব্দ কাটা নেই।",
            'bs' => "Napišite kratki i privlačni opis za ovaj proizvod: {$productName}. Uverite se da je opis jasan, potpun i da nijedna reč nije presečena.",
            'ca' => "Escriviu una descripció curta i atractiva per a aquest producte: {$productName}. Assegureu-vos que la descripció sigui clara, completa i sense cap tall.",
            'cs' => "Napište krátký a atraktivní popis pro tento produkt: {$productName}. Ujistěte se, že popis je jasný, úplný a že žádné slovo není přerušeno.",
            'cy' => "Ysgrifennwch ddisgrifiad byr a deniadol ar gyfer y cynnyrch hwn: {$productName}. Gwnewch yn siŵr bod y disgrifiad yn glir, cyflawn ac nad oes unrhyw air wedi'i dorri.",
            'da' => "Skriv en kort og engagerende produktbeskrivelse for: {$productName}. Sørg for, at beskrivelsen er klar, fuldstændig og uden afbrudte ord.",
            'de' => "Schreiben Sie eine kurze und ansprechende Produktbeschreibung für: {$productName}. Achten Sie darauf, dass die Beschreibung klar, vollständig und ohne unterbrochene Wörter ist.",
            'en' => "Write a short and engaging product description for: {$productName}. Make sure the description is clear, complete, and does not contain any cut-off words.",
            'fa' => "یک توضیح کوتاه و جذاب برای این محصول بنویسید: {$productName}. مطمئن شوید که توضیحات کامل، واضح و بدون هیچ کلمه بریده‌ای است.",
            'fi' => "Kirjoita lyhyt ja houkutteleva tuotekuvaus: {$productName}. Varmista, että kuvaus on selkeä, täydellinen eikä sisällä keskeytyksiä.",
            'fr' => "Écrivez une description courte et engageante pour : {$productName}. Assurez-vous que la description est claire, complète et sans aucun mot coupé.",
            'gl' => "Escriba unha descrición curta e atractiva para este produto: {$productName}. Asegúrese de que a descrición sexa clara, completa e sen palabras cortadas.",
            'gu' => "આ ઉત્પાદન માટે ટૂચું અને આકર્ષક વર્ણન લખો: {$productName}. એશ્યો કરો કે વર્ણન સ્પષ્ટ, પૂર્ણ અને કોઈપણ શબ્દ કાપેલા નથી.",
            'hi' => "इस उत्पाद का एक छोटा और आकर्षक विवरण लिखें: {$productName}. सुनिश्चित करें कि विवरण स्पष्ट, पूरा हो और किसी शब्द का टुकड़ा न हो।",
            'hn' => "इस उत्पाद का एक छोटा और आकर्षक विवरण लिखें: {$productName}. सुनिश्चित करें कि विवरण स्पष्ट, पूरा हो और किसी शब्द का टुकड़ा न हो।",
            'hr' => "Napišite kratki i privlačni opis za ovaj proizvod: {$productName}. Pobrinite se da opis bude jasan, potpun i da nijedna riječ nije prekinuta.",
            'hu' => "Írjon egy rövid és vonzó termékleírást: {$productName}. Győződjön meg arról, hogy a leírás világos, teljes és nem tartalmaz félbeszakadt szavakat.",
            'id' => "Tulis deskripsi produk singkat dan menarik untuk: {$productName}. Pastikan deskripsinya jelas, lengkap, dan tidak ada kata yang terpotong.",
            'it' => "Scrivi una breve e coinvolgente descrizione del prodotto per: {$productName}. Assicurati che la descrizione sia chiara, completa e senza parole troncate.",
            'ja' => "この製品の短く魅力的な説明を書いてください: {$productName}. 説明が明確で完全であり、切り取られた言葉がないことを確認してください。",
            'jv' => "Tulis deskripsi produk singkat lan menarik kanggo: {$productName}. Pastikan deskripsine jelas, lengkap, lan ora ana tembung sing dipotong.",
            'ka' => "წერეთ მოკლე და მიმზიდველი პროდუქტის აღწერა: {$productName}. დარწმუნდით, რომ აღწერა მკაფიო და სრულყოფილია, და რომ არც ერთი სიტყვა არ არის დაჭრილი.",
            'kn' => "ಈ ಉತ್ಪನ್ನಕ್ಕೆ ಚಿಕ್ಕ ಮತ್ತು ಆಕರ್ಷಕ ವಿವರಣೆ ಬರೆಯಿರಿ: {$productName}. ವಿವರಣೆಯು ಸ್ಪಷ್ಟ, ಪೂರ್ಣವಾಗಿದೆ ಮತ್ತು ಯಾವುದೇ ಪದವನ್ನು ಕತ್ತರಿಸುವುದಿಲ್ಲ ಎಂಬುದನ್ನು ಖಚಿತಪಡಿಸಿಕೊಳ್ಳಿ.",
            'ko' => "이 제품에 대한 간단하고 매력적인 설명을 작성하세요: {$productName}. 설명이 명확하고 완전하며, 잘린 단어가 없도록 하세요.",
            'mr' => "या उत्पादनासाठी एक छोटं आणि आकर्षक वर्णन लिहा: {$productName}. हे सुनिश्चित करा की वर्णन स्पष्ट, पूर्ण आहे आणि कोणताही शब्द कापला गेलेला नाही.",
            'ms' => "Tulis deskripsi produk yang ringkas dan menarik untuk: {$productName}. Pastikan deskripsinya jelas, lengkap, dan tidak ada kata yang terpotong.",
            'my' => "ဤထုတ်ကုန်အတွက်အတိုချုံးပြီးဆွဲဆောင်မှုရှိသောဖော်ပြချက်ရေးပါ: {$productName}. ဖော်ပြချက်သည်ရှင်းလင်းပြီးပြည့်စုံပါစေ၊ နှင့်တစ်ခုတည်းသောစကားလုံးပျက်သွားမည်မဟုတ်ပါ။",
            'ne' => "यस उत्पादनको लागि छोटो र आकर्षक विवरण लेख्नुहोस्: {$productName}. विवरण स्पष्ट र पूर्ण भएको सुनिश्चित गर्नुहोस् र कुनै शब्द टुक्रा नगर्ने कुरा पनि सुनिश्चित गर्नुहोस्।",
            'nl' => "Schrijf een korte en aantrekkelijke productomschrijving voor: {$productName}. Zorg ervoor dat de beschrijving duidelijk, volledig is en geen onderbroken woorden bevat.",
            'ta' => "இந்த பொருளுக்கு குறுகிய மற்றும் ஈர்க்கக்கூடிய விளக்கத்தை எழுதவும்: {$productName}. விளக்கம் தெளிவாகவும் முழுமையாகவும் இருப்பதை உறுதிசெய்யவும், மேலும் எந்த வார்த்தைகளும் துண்டிக்கப்பட்டிருக்கக் கூடாது.",
            'tr' => "Bu ürün için kısa ve çekici bir açıklama yazın: {$productName}. Açıklamanın açık, tam ve hiçbir kelimenin kesilmediğinden emin olun.",
            'uk' => "Напишіть короткий і привабливий опис для цього продукту: {$productName}. Переконайтесь, що опис ясний, повний і не містить обрізаних слів.",
            'ur' => "اس پروڈکٹ کے لئے ایک مختصر اور دلچسپ تفصیل لکھیں: {$productName}. یہ یقینی بنائیں کہ تفصیل واضح، مکمل ہو اور کوئی لفظ کٹا نہ ہو۔",
            'vi' => "Viết một mô tả ngắn gọn và hấp dẫn cho sản phẩm này: {$productName}. Đảm bảo mô tả rõ ràng, đầy đủ và không có từ nào bị cắt ngắn.",
            'zh' => "为此产品写一个简短而吸引人的描述：{$productName}. 确保描述清晰、完整，并且没有任何单词被截断。",
        ];
        $prompt = $customPrompt ?? ($prompts[$languageCode] ?? $prompts['af']);

        $settings = $this->getAISettings();
        $result = $this->generateAIResponse($prompt, $settings['ai_method'], $settings['api_keys']);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['description' => $result['description']]);
    }

    public function generateDescription(Request $request)
    {
        $productName = $request->input('product_name');
        $customPrompt = $request->input('prompt');
        $existingDescription = $request->input('existing_description');

        if (!$productName) {
            return response()->json(['message' => 'Product name is required.'], 422);
        }

        $systemMessage = "You are a helpful assistant that writes product descriptions for e-commerce listings.";
        $messages = [['role' => 'system', 'content' => $systemMessage]];

        if ($existingDescription && $customPrompt) {
            $messages[] = ['role' => 'user', 'content' => "Here is the current product description:\n\n{$existingDescription}"];
            $messages[] = ['role' => 'user', 'content' => $customPrompt];
        } else {
            $initialPrompt = $customPrompt ?: "Write a compelling and SEO-friendly product description for: {$productName} without including any title or heading. It should be informative, highlight key features and benefits, and sound natural and engaging. Keep it concise, around 300–400 words maximum.";
            $messages[] = ['role' => 'user', 'content' => $initialPrompt];
        }

        // Flatten messages into prompt
        $formattedPrompt = collect($messages)->map(fn($m) => "{$m['role']}: {$m['content']}")->implode("\n");

        $settings = $this->getAISettings();
        $result = $this->generateAIResponse($formattedPrompt, $settings['ai_method'], $settings['api_keys']);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['description' => $result['description']]);
    }


    public function getSuggestedPrompts(Request $request)
    {
        $productName = $request->get('product_name');

        if (!$productName || strlen($productName) < 3) {
            return response()->json(['prompts' => []]);
        }

        $prompt = "Generate 25 engaging, SEO-optimized product description ideas for the product \"$productName\". Each description should be concise, relevant, and appealing to eCommerce customers. Focus on highlighting the key benefits, features, and selling points of the product in a simple and straightforward manner. Avoid any overly technical jargon or specific thematic focus. Provide these descriptions in a numbered list format, one per line, without any introductory or additional text.";

        $settings = $this->getAISettings();
        $result = $this->generateAIResponse($prompt, $settings['ai_method'], $settings['api_keys']);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 500);
        }

        $prompts = collect(preg_split("/\r\n|\n|\r/", $result['description'] ?? ''))
            ->map(function ($line) {
                $line = preg_replace('/\*\*(.*?)\*\*/', '$1', $line);
                $line = preg_replace('/^Here are \d+.+?:/i', '', $line);
                $line = preg_replace('/^\d+\)?\.?\s*/', '', $line);
                return trim($line);
            })
            ->filter()
            ->values();

        return response()->json(['prompts' => $prompts]);
    }

    private function getAISettings()
    {
        $ai_settings = json_decode(app(SettingService::class)->getSettings('ai_settings', true), true);
        $ai_method = $ai_settings['ai_method'] ?? 'gemini_api';

        $api_keys = [
            'gemini_api' => json_decode(app(SettingService::class)->getSettings('gemini_api_key', true), true)['gemini_api_key'] ?? null,
            'openrouter_api' => json_decode(app(SettingService::class)->getSettings('openrouter_api_key', true), true)['openrouter_api_key'] ?? null,
        ];

        return compact('ai_method', 'api_keys');
    }

    private function generateAIResponse($prompt, $ai_method, $api_keys)
    {
        if ($ai_method == 'gemini_api') {
            if (empty($api_keys['gemini_api'])) {
                return ['error' => 'Invalid or empty Gemini API Key.'];
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$api_keys['gemini_api']}", [
                'contents' => [[
                    'parts' => [['text' => $prompt]]
                ]]
            ]);

            if ($response->failed()) {
                return ['error' => 'Failed to generate description.'];
            }

            return ['description' => $response->json('candidates.0.content.parts.0.text') ?? 'No description generated.'];
        }

        if ($ai_method == 'openrouter_api') {
            if (empty($api_keys['openrouter_api'])) {
                return ['error' => 'Invalid or empty OpenRouter API Key.'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $api_keys['openrouter_api'],
                'HTTP-Referer' => config('app.url'),
            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                // 'model' => 'mistralai/mistral-7b-instruct',
                'model' => 'openai/gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 300,
            ]);

            if ($response->failed()) {
                return ['error' => 'Failed to generate description.'];
            }

            return ['description' => $response->json('choices.0.message.content') ?? 'No description generated.'];
        }

        return ['error' => 'Unsupported AI method specified in settings.'];
    }
}
