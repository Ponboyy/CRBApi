<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Illuminate\Support\Facades\Cache;   

class CurrencyController extends Controller
{
    private $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.cbr_api.url');
    }

    public function getCurrencyRates()
    {
        $cachedData = $this->getCachedData();
        if ($cachedData) {
            return response()->json($cachedData);
        }

        // Если данные не кэшированы, извлекаем их из внешнего API
        $response = $this->fetchDataFromApi();
        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data from the external service'], 500);
        }

        // Обработка XML
        $xmlData = $response->body();
        $currencyData = $this->parseXmlData($xmlData);

        // Кэшируем данные
        $this->cacheData($currencyData);

        return response()->json($currencyData);
    }

    private function fetchDataFromApi()
    {
        return Http::get($this->apiUrl);
    }

    private function parseXmlData($xmlString)
    {
        $xml = new SimpleXMLElement($xmlString);
        $data = [];

        foreach ($xml->Valute as $valute) {
            $data[(string)$valute->CharCode] = (float)str_replace(',', '.', (string)$valute->Value);
        }

        return $data;
    }

    private function getCachedData()
{
    if (Cache::has('currency_rates')) {
        return Cache::get('currency_rates');
    }

    return null;
}

private function cacheData($data)
{
    Cache::put('currency_rates', $data, now()->addHours(24));
}
}
