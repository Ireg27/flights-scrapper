<?php

namespace App\Http\Controllers;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Dusk\Browser;
use Facebook\WebDriver\WebDriverBy;

class FlightScrapperController extends Controller
{
    /**
     * Search for flights on the Ryan air website.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchFlights(Request $request): JsonResponse
    {
        $departureAirport = 'Milan';
        $departureCode = 'MXP';
        $destinationAirport = 'Vienna';
        $destinationCode = 'VIE';
        $request->validate([
            'departureDate' => 'required|date_format:Y-m-d',
        ]);
        $departureDate = $request->input('departureDate');

        $host = env('CHROME_DRIVER_URL');
        $driver = RemoteWebDriver::create($host, DesiredCapabilities::chrome());
        $browser = new Browser($driver);

        $browser->visit('https://www.ryanair.com/')
            ->waitFor('.cookie-popup-with-overlay__box')
            ->press('.cookie-popup-with-overlay__button')
            ->click("[data-ref='flight-search-trip-type__one-way-trip'] button")
            ->clear('#input-button__departure')
            ->type('#input-button__departure', $departureAirport)
            ->keys('#input-button__departure', '{enter}')
            ->click("span[data-id='$departureCode']")
            ->pause(500)
            ->type('#input-button__destination', $destinationAirport)
            ->click("span[data-id='$destinationCode']")
            ->pause(500)
            ->click("div.calendar-body__cell[data-id='$departureDate']")
            ->click('.flight-search-widget__start-search')
            ->pause(500);

        if ($browser->driver->getCurrentURL() !== 'https://www.ryanair.com/') {
            $redirectedURL = $browser->driver->getCurrentURL();
            $browser->visit($redirectedURL)
                ->waitFor('.ng-tns-c174-7');
        }

        $flightCards = $browser->elements('.flight-card');
        $flightDetails = [];
        foreach ($flightCards as $card) {
            $details = $card->findElement(WebDriverBy::cssSelector('.flight-card__info'))->getText();
            $price = $card->findElement(WebDriverBy::cssSelector('.flight-card-summary__new-value'))->getText();

            $data = explode("\n", $details);

            $departureTime = $data[0];
            $departureAirport = $data[1];
            $flightNumber = $data[2];
            $duration = $data[3];
            $arrivalTime = $data[4];
            $arrivalAirport = $data[5];

            $formattedDetail = [
                'departure_time' => $departureTime,
                'departure_airport' => $departureAirport,
                'flight_number' => $flightNumber,
                'duration' => $duration,
                'arrival_time' => $arrivalTime,
                'arrival_airport' => $arrivalAirport,
                'price' => $price,
            ];

            $flightDetails[] = $formattedDetail;
        }

        $browser->quit();

        return response()->json($flightDetails);
    }
}
