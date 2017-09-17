<?php

namespace Tests\Unit;

use App\Http\ResponseObject;
use Carbon\Carbon;
use PHPUnit_Framework_TestCase;

class ResponseObjectTest extends PHPUnit_Framework_TestCase
{
    public function testToJson()
    {
        $timezone    = new \DateTimeZone("America/Toronto");
        $carbon_xmas = new Carbon('2015-12-25', $timezone);
        $now         = new \DateTime('now', $timezone);
        $yesterday   = new \DateTime('yesterday', $timezone);
        $next_week   = new \DateTime('next week', $timezone);
        $test_object = [
            "carbon"       => $carbon_xmas,
            "datetime"     => $now,
            "nested_array" => [
                [1, 2, $yesterday],
                ["lorem" => "ipsum"]
            ],
            "nested_assoc" => [
                "hello"    => "world",
                "datetime" => $next_week
            ],
            "object" => (object)["object_key" => "object_value"]
        ];

        $response = new ResponseObject($test_object);
        $json     = $response->toJson();
        $result   = json_decode($json, true);

        $this->assertNotNull($result['response']);
        // dates should be formatted in ISO8601
        $this->assertEquals($this->getArrayForDate($carbon_xmas), $result['response']['carbon']);
        $this->assertEquals($this->getArrayForDate($now), $result['response']['datetime']);
        $this->assertEquals(
            [
                [1, 2, $this->getArrayForDate($yesterday)],
                ["lorem" => "ipsum"]
            ],
            $result['response']['nested_array']
        );

        $this->assertEquals(
            ["hello" => "world", "datetime" => $this->getArrayForDate($next_week)],
            $result['response']['nested_assoc']
        );

        $this->assertEquals(["object_key" => "object_value"], $result['response']['object']);
    }

    public function testEmptyResponse()
    {
        $response = new ResponseObject();
        $json     = $response->toJson();
        $result   = json_decode($json, true);
        $this->assertNull($result['response']);
        $this->assertEquals(200, $response->getStatus());
    }

    /**
     * @param $date \DateTime
     */
    private function getArrayForDate($date)
    {
        return ["date" => $date->format(DATE_ISO8601), "timezone" => $date->getTimezone()->getName()];
    }
}
