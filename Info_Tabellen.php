<?php

const TBL_INFO_ABTEILUNGEN = [
    ['ID' => '1', 'description' => 'Fakultät Sowi'          , 'Kennung' => '104', 'nr' => '4', 'name' => 'sowi'    ],
    ['ID' => '2', 'description' => 'Abteilung VWL'          , 'Kennung' => '103', 'nr' => '3', 'name' => 'jura_vwl'],
    ['ID' => '3', 'description' => 'Abteilung Jura'         , 'Kennung' => '101', 'nr' => '1', 'name' => 'jura_vwl'],
    ['ID' => '4', 'description' => 'Fakultät BWL'           , 'Kennung' => '102', 'nr' => '2', 'name' => 'bwl'     ],
    ['ID' => '5', 'description' => 'Philosophische Fakultät', 'Kennung' => '105', 'nr' => '5', 'name' => 'phil'    ],
    ['ID' => '6', 'description' => 'Fakultät WiFo/WiMa'     , 'Kennung' => '106', 'nr' => '6', 'name' => 'wifo_wim'],
];

const TBL_INFO_FACHSCHAFTEN = [
    ['ID' => '1' , 'name' => 'bwl'   , 'description' => 'Betriebswirtschaftslehre'                ],
    ['ID' => '2' , 'name' => 'fga'   , 'description' => 'Geschichte und Altertumswissenschaften'  ],
    ['ID' => '3' , 'name' => 'fim'   , 'description' => 'Informatik und Mathematik'               ],
    ['ID' => '4' , 'name' => 'jura'  , 'description' => 'Jura'                                    ],
    ['ID' => '5' , 'name' => 'mkw'   , 'description' => 'Medien- und Kommunikationswissenschaften'],
    ['ID' => '6' , 'name' => 'psycho', 'description' => 'Psychologie'                             ],
    ['ID' => '7' , 'name' => 'sopo'  , 'description' => 'Soziologie und Politikwissenschaft'      ],
    ['ID' => '8' , 'name' => 'split' , 'description' => 'Sprach- und Literaturwissenschaft'       ],
    ['ID' => '9' , 'name' => 'vwl'   , 'description' => 'Volkswirtschaftslehre'                   ],
    ['ID' => '10', 'name' => 'wipäd' , 'description' => 'Wirtschaftspädagogik'                    ],
];

const TBL_INFO_FAKULTÄTEN = [
    ['ID' => '20', 'description' => 'Fakultät für Rechtswissenschaft und Volkswirtschaftslehre'   , 'nr' => '1', 'name' => 'jura_vwl'],
    ['ID' => '21', 'description' => 'Fakultät für Betriebswirtschaftslehre                    '   , 'nr' => '2', 'name' => 'bwl'     ],
    ['ID' => '22', 'description' => 'Fakultät für Sozialwissenschaften'                           , 'nr' => '3', 'name' => 'sowi'    ],
    ['ID' => '23', 'description' => 'Philosophische Fakultät'                                     , 'nr' => '4', 'name' => 'phil'    ],
    ['ID' => '24', 'description' => 'Fakultät für Wirtschaftsinformatik und Wirtschaftsmathematik', 'nr' => '5', 'name' => 'wifo_wim'],
];

const TBL_INFO_WÄHLENDENGRUPPE = [
    ['ID' => '1', 'nr' => '1', 'name' => 'lehrende'     , 'description' => 'Hochschullehrende'        ],
    ['ID' => '2', 'nr' => '2', 'name' => 'akademische'  , 'description' => 'Akademische Mitarbeitende'],
    ['ID' => '3', 'nr' => '3', 'name' => 'studierende'  , 'description' => 'Studierende'              ],
    ['ID' => '4', 'nr' => '4', 'name' => 'promovierende', 'description' => 'Promovierende'            ],
    ['ID' => '5', 'nr' => '5', 'name' => 'sonstige'     , 'description' => 'Sonstige Mitarbeiter'     ],
];


if (!function_exists('create_lookup_map')) {
    function create_lookup_map(array $table, string $keyColumn): array {
        $map = [];
        foreach ($table as $row) {
            if (isset($row[$keyColumn])) {
                $map[$row[$keyColumn]] = $row;
            }
        }
        return $map;
    }
}