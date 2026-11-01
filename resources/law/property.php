<?php

/*
 * Property tax on land — Tax Code of Georgia, Art. 204 (consolidated 30/09/2026).
 * Base annual rates in GEL per hectare, by municipality. Municipality names are in English;
 * the occupied municipalities are kept because the Code lists them.
 *
 * Tbilisi's own villages carry separate arable rates (204.1.ა rows 2 and 6). The ones named as
 * places are included under 'tbilisi:<village>'; the garden plots described by location
 * ("garden plots from Gldani village to Avchala", etc.) are not and resolve as Tbilisi.
 */

$arable = [
    100 => ['Tbilisi', 'Marneuli'],
    95 => ['Bolnisi', 'Gardabani', 'tbilisi:Tsavkisi', 'tbilisi:Kojori', 'tbilisi:Tabakhmela', 'tbilisi:Shindisi',
        'tbilisi:Dideba', 'tbilisi:Kveseti', 'tbilisi:Samadlo', 'tbilisi:Kiketi', 'tbilisi:Nasagurali', 'tbilisi:Akhaldaba',
        'tbilisi:Didi Lilo', 'tbilisi:Varketili'],
    94 => ['Rustavi', 'Batumi', 'Gagra', 'Gali', 'Gudauta', 'Gulripshi', 'Ochamchire', 'Sokhumi', 'Tkvarcheli'],
    87 => ['Kobuleti', 'Khelvachauri', 'Gurjaani', 'Dedoplistskaro'],
    86 => ['Telavi', 'Lagodekhi', 'Sighnaghi'],
    82 => ['Kvareli', 'Gori', 'Mtskheta', 'Akhmeta', 'Dmanisi', 'Eredvi', 'Tighvi', 'tbilisi:Dighomi', 'tbilisi:Mshralkhevi',
        'tbilisi:Zahesi', 'tbilisi:Gldani', 'tbilisi:Didgori', 'tbilisi:Zurgovana', 'tbilisi:Telovana', 'tbilisi:Dzveli Vedzisi',
        'tbilisi:Agaraki', 'tbilisi:Tkhinvali'],
    81 => ['Kaspi', 'Tetritskaro', 'Samtredia'],
    79 => ['Sagarejo', 'Kareli', 'Khashuri'],
    77 => ['Kurta', 'Tsalka', 'Abasha', 'Zugdidi', 'Akhalkalaki', 'Akhaltsikhe'],
    76 => ['Martvili', 'Senaki', 'Khobi', 'Poti', 'Ninotsminda'],
    73 => ['Akhalgori', 'Vani', 'Zestaponi', 'Lanchkhuti', 'Ozurgeti'],
    71 => ['Baghdati', 'Terjola', 'Khoni', 'Kutaisi'],
    67 => ['Tsalenjikha', 'Tskaltubo', 'Chkhorotsku'],
    65 => ['Sachkhere', 'Tsageri', 'Tskhinvali'],
    61 => ['Ambrolauri', 'Dusheti', 'Tianeti', 'Adigeni', 'Borjomi'],
    60 => ['Aspindza', 'Tkibuli', 'Khulo', 'Keda'],
    56 => ['Shuakhevi', 'Kharagauli', 'Chiatura', 'Lentekhi', 'Oni', 'Chokhatauri', 'Mestia', 'Stepantsminda', 'Java'],
];

/* [hayfield, pasture] => municipalities. Stepantsminda is listed here as Kazbegi. */
$meadow = [
    '20/16' => ['Abasha', 'Akhalkalaki', 'Gori', 'Batumi', 'Bolnisi', 'Gagra', 'Gali', 'Gardabani', 'Gudauta', 'Gulripshi',
        'Gurjaani', 'Dmanisi', 'Zugdidi', 'Tbilisi', 'Tetritskaro', 'Telavi', 'Lagodekhi', 'Lanchkhuti', 'Marneuli', 'Mtskheta',
        'Ninotsminda', 'Ozurgeti', 'Ochamchire', 'Rustavi', 'Samtredia', 'Senaki', 'Sighnaghi', 'Sokhumi', 'Kutaisi', 'Kobuleti',
        'Kvareli', 'Tsalka', 'Tskaltubo', 'Khelvachauri', 'Khobi', 'Poti'],
    '20/7' => ['Dedoplistskaro'],
    '19/15' => ['Adigeni', 'Aspindza', 'Akhalgori', 'Baghdati', 'Borjomi', 'Vani', 'Zestaponi', 'Terjola', 'Tianeti', 'Kaspi',
        'Martvili', 'Sagarejo', 'Sachkhere', 'Tkibuli', 'Kareli', 'Keda', 'Shuakhevi', 'Chokhatauri', 'Chkhorotsku',
        'Tsalenjikha', 'Kharagauli', 'Khashuri', 'Khoni', 'Khulo', 'Akhaltsikhe'],
    '16/10' => ['Ambrolauri', 'Dusheti', 'Lentekhi', 'Mestia', 'Oni', 'Kazbegi', 'Stepantsminda', 'Tsageri', 'Tskhinvali',
        'Chiatura', 'Java'],
    '16/5' => ['Akhmeta'],
];

$flip = static function (array $groups, callable $rate): array {
    $out = [];
    foreach ($groups as $key => $places) {
        foreach ($places as $place) {
            $out[strtolower($place)] = $rate($key);
        }
    }
    return $out;
};

return [
    'arable' => $flip($arable, fn ($gel) => (float) $gel),
    'hayfield' => $flip($meadow, fn ($pair) => (float) explode('/', $pair)[0]),
    'pasture' => $flip($meadow, fn ($pair) => (float) explode('/', $pair)[1]),
    'non_agricultural_per_m2' => 0.24,   // 204.2.ა
    'natural_resource_per_ha' => 3.0,    // 204.3 — "not more than 3 GEL"
];
