<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoleHasPermissionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('role_has_permissions')->delete();
        
        \DB::table('role_has_permissions')->insert(array (
            0 => 
            array (
                'permission_id' => 1,
                'role_id' => 1,
            ),
            1 => 
            array (
                'permission_id' => 1,
                'role_id' => 3,
            ),
            2 => 
            array (
                'permission_id' => 2,
                'role_id' => 1,
            ),
            3 => 
            array (
                'permission_id' => 2,
                'role_id' => 3,
            ),
            4 => 
            array (
                'permission_id' => 3,
                'role_id' => 1,
            ),
            5 => 
            array (
                'permission_id' => 4,
                'role_id' => 1,
            ),
            6 => 
            array (
                'permission_id' => 5,
                'role_id' => 1,
            ),
            7 => 
            array (
                'permission_id' => 6,
                'role_id' => 1,
            ),
            8 => 
            array (
                'permission_id' => 6,
                'role_id' => 3,
            ),
            9 => 
            array (
                'permission_id' => 7,
                'role_id' => 1,
            ),
            10 => 
            array (
                'permission_id' => 7,
                'role_id' => 3,
            ),
            11 => 
            array (
                'permission_id' => 8,
                'role_id' => 1,
            ),
            12 => 
            array (
                'permission_id' => 9,
                'role_id' => 1,
            ),
            13 => 
            array (
                'permission_id' => 10,
                'role_id' => 1,
            ),
            14 => 
            array (
                'permission_id' => 11,
                'role_id' => 1,
            ),
            15 => 
            array (
                'permission_id' => 11,
                'role_id' => 3,
            ),
            16 => 
            array (
                'permission_id' => 12,
                'role_id' => 1,
            ),
            17 => 
            array (
                'permission_id' => 12,
                'role_id' => 3,
            ),
            18 => 
            array (
                'permission_id' => 13,
                'role_id' => 1,
            ),
            19 => 
            array (
                'permission_id' => 14,
                'role_id' => 1,
            ),
            20 => 
            array (
                'permission_id' => 15,
                'role_id' => 1,
            ),
            21 => 
            array (
                'permission_id' => 16,
                'role_id' => 1,
            ),
            22 => 
            array (
                'permission_id' => 16,
                'role_id' => 3,
            ),
            23 => 
            array (
                'permission_id' => 17,
                'role_id' => 1,
            ),
            24 => 
            array (
                'permission_id' => 17,
                'role_id' => 3,
            ),
            25 => 
            array (
                'permission_id' => 18,
                'role_id' => 1,
            ),
            26 => 
            array (
                'permission_id' => 19,
                'role_id' => 1,
            ),
            27 => 
            array (
                'permission_id' => 20,
                'role_id' => 1,
            ),
            28 => 
            array (
                'permission_id' => 21,
                'role_id' => 1,
            ),
            29 => 
            array (
                'permission_id' => 21,
                'role_id' => 3,
            ),
            30 => 
            array (
                'permission_id' => 22,
                'role_id' => 1,
            ),
            31 => 
            array (
                'permission_id' => 22,
                'role_id' => 3,
            ),
            32 => 
            array (
                'permission_id' => 23,
                'role_id' => 1,
            ),
            33 => 
            array (
                'permission_id' => 24,
                'role_id' => 1,
            ),
            34 => 
            array (
                'permission_id' => 25,
                'role_id' => 1,
            ),
            35 => 
            array (
                'permission_id' => 26,
                'role_id' => 1,
            ),
            36 => 
            array (
                'permission_id' => 26,
                'role_id' => 3,
            ),
            37 => 
            array (
                'permission_id' => 27,
                'role_id' => 1,
            ),
            38 => 
            array (
                'permission_id' => 27,
                'role_id' => 3,
            ),
            39 => 
            array (
                'permission_id' => 28,
                'role_id' => 1,
            ),
            40 => 
            array (
                'permission_id' => 29,
                'role_id' => 1,
            ),
            41 => 
            array (
                'permission_id' => 30,
                'role_id' => 1,
            ),
            42 => 
            array (
                'permission_id' => 31,
                'role_id' => 1,
            ),
            43 => 
            array (
                'permission_id' => 31,
                'role_id' => 3,
            ),
            44 => 
            array (
                'permission_id' => 32,
                'role_id' => 1,
            ),
            45 => 
            array (
                'permission_id' => 32,
                'role_id' => 3,
            ),
            46 => 
            array (
                'permission_id' => 33,
                'role_id' => 1,
            ),
            47 => 
            array (
                'permission_id' => 34,
                'role_id' => 1,
            ),
            48 => 
            array (
                'permission_id' => 35,
                'role_id' => 1,
            ),
            49 => 
            array (
                'permission_id' => 36,
                'role_id' => 1,
            ),
            50 => 
            array (
                'permission_id' => 36,
                'role_id' => 3,
            ),
            51 => 
            array (
                'permission_id' => 37,
                'role_id' => 1,
            ),
            52 => 
            array (
                'permission_id' => 37,
                'role_id' => 3,
            ),
            53 => 
            array (
                'permission_id' => 38,
                'role_id' => 1,
            ),
            54 => 
            array (
                'permission_id' => 39,
                'role_id' => 1,
            ),
            55 => 
            array (
                'permission_id' => 40,
                'role_id' => 1,
            ),
            56 => 
            array (
                'permission_id' => 41,
                'role_id' => 1,
            ),
            57 => 
            array (
                'permission_id' => 41,
                'role_id' => 3,
            ),
            58 => 
            array (
                'permission_id' => 42,
                'role_id' => 1,
            ),
            59 => 
            array (
                'permission_id' => 42,
                'role_id' => 3,
            ),
            60 => 
            array (
                'permission_id' => 43,
                'role_id' => 1,
            ),
            61 => 
            array (
                'permission_id' => 44,
                'role_id' => 1,
            ),
            62 => 
            array (
                'permission_id' => 45,
                'role_id' => 1,
            ),
            63 => 
            array (
                'permission_id' => 46,
                'role_id' => 1,
            ),
            64 => 
            array (
                'permission_id' => 46,
                'role_id' => 3,
            ),
            65 => 
            array (
                'permission_id' => 47,
                'role_id' => 1,
            ),
            66 => 
            array (
                'permission_id' => 47,
                'role_id' => 3,
            ),
            67 => 
            array (
                'permission_id' => 48,
                'role_id' => 1,
            ),
            68 => 
            array (
                'permission_id' => 49,
                'role_id' => 1,
            ),
            69 => 
            array (
                'permission_id' => 50,
                'role_id' => 1,
            ),
            70 => 
            array (
                'permission_id' => 51,
                'role_id' => 1,
            ),
            71 => 
            array (
                'permission_id' => 51,
                'role_id' => 3,
            ),
            72 => 
            array (
                'permission_id' => 52,
                'role_id' => 1,
            ),
            73 => 
            array (
                'permission_id' => 52,
                'role_id' => 3,
            ),
            74 => 
            array (
                'permission_id' => 53,
                'role_id' => 1,
            ),
            75 => 
            array (
                'permission_id' => 54,
                'role_id' => 1,
            ),
            76 => 
            array (
                'permission_id' => 55,
                'role_id' => 1,
            ),
            77 => 
            array (
                'permission_id' => 56,
                'role_id' => 1,
            ),
            78 => 
            array (
                'permission_id' => 56,
                'role_id' => 3,
            ),
            79 => 
            array (
                'permission_id' => 57,
                'role_id' => 1,
            ),
            80 => 
            array (
                'permission_id' => 57,
                'role_id' => 3,
            ),
            81 => 
            array (
                'permission_id' => 58,
                'role_id' => 1,
            ),
            82 => 
            array (
                'permission_id' => 59,
                'role_id' => 1,
            ),
            83 => 
            array (
                'permission_id' => 60,
                'role_id' => 1,
            ),
            84 => 
            array (
                'permission_id' => 61,
                'role_id' => 1,
            ),
            85 => 
            array (
                'permission_id' => 61,
                'role_id' => 3,
            ),
            86 => 
            array (
                'permission_id' => 62,
                'role_id' => 1,
            ),
            87 => 
            array (
                'permission_id' => 62,
                'role_id' => 3,
            ),
            88 => 
            array (
                'permission_id' => 63,
                'role_id' => 1,
            ),
            89 => 
            array (
                'permission_id' => 64,
                'role_id' => 1,
            ),
            90 => 
            array (
                'permission_id' => 65,
                'role_id' => 1,
            ),
            91 => 
            array (
                'permission_id' => 66,
                'role_id' => 1,
            ),
            92 => 
            array (
                'permission_id' => 66,
                'role_id' => 3,
            ),
            93 => 
            array (
                'permission_id' => 67,
                'role_id' => 1,
            ),
            94 => 
            array (
                'permission_id' => 67,
                'role_id' => 3,
            ),
            95 => 
            array (
                'permission_id' => 68,
                'role_id' => 1,
            ),
            96 => 
            array (
                'permission_id' => 69,
                'role_id' => 1,
            ),
            97 => 
            array (
                'permission_id' => 70,
                'role_id' => 1,
            ),
            98 => 
            array (
                'permission_id' => 71,
                'role_id' => 1,
            ),
            99 => 
            array (
                'permission_id' => 71,
                'role_id' => 3,
            ),
            100 => 
            array (
                'permission_id' => 72,
                'role_id' => 1,
            ),
            101 => 
            array (
                'permission_id' => 72,
                'role_id' => 3,
            ),
            102 => 
            array (
                'permission_id' => 73,
                'role_id' => 1,
            ),
            103 => 
            array (
                'permission_id' => 74,
                'role_id' => 1,
            ),
            104 => 
            array (
                'permission_id' => 75,
                'role_id' => 1,
            ),
            105 => 
            array (
                'permission_id' => 76,
                'role_id' => 1,
            ),
            106 => 
            array (
                'permission_id' => 76,
                'role_id' => 3,
            ),
            107 => 
            array (
                'permission_id' => 77,
                'role_id' => 1,
            ),
            108 => 
            array (
                'permission_id' => 77,
                'role_id' => 3,
            ),
            109 => 
            array (
                'permission_id' => 78,
                'role_id' => 1,
            ),
            110 => 
            array (
                'permission_id' => 79,
                'role_id' => 1,
            ),
            111 => 
            array (
                'permission_id' => 80,
                'role_id' => 1,
            ),
            112 => 
            array (
                'permission_id' => 81,
                'role_id' => 1,
            ),
            113 => 
            array (
                'permission_id' => 81,
                'role_id' => 3,
            ),
            114 => 
            array (
                'permission_id' => 82,
                'role_id' => 1,
            ),
            115 => 
            array (
                'permission_id' => 82,
                'role_id' => 3,
            ),
            116 => 
            array (
                'permission_id' => 83,
                'role_id' => 1,
            ),
            117 => 
            array (
                'permission_id' => 84,
                'role_id' => 1,
            ),
            118 => 
            array (
                'permission_id' => 85,
                'role_id' => 1,
            ),
            119 => 
            array (
                'permission_id' => 86,
                'role_id' => 1,
            ),
            120 => 
            array (
                'permission_id' => 86,
                'role_id' => 3,
            ),
            121 => 
            array (
                'permission_id' => 87,
                'role_id' => 1,
            ),
            122 => 
            array (
                'permission_id' => 87,
                'role_id' => 3,
            ),
            123 => 
            array (
                'permission_id' => 88,
                'role_id' => 1,
            ),
            124 => 
            array (
                'permission_id' => 89,
                'role_id' => 1,
            ),
            125 => 
            array (
                'permission_id' => 90,
                'role_id' => 1,
            ),
            126 => 
            array (
                'permission_id' => 91,
                'role_id' => 1,
            ),
            127 => 
            array (
                'permission_id' => 91,
                'role_id' => 3,
            ),
            128 => 
            array (
                'permission_id' => 92,
                'role_id' => 1,
            ),
            129 => 
            array (
                'permission_id' => 92,
                'role_id' => 3,
            ),
            130 => 
            array (
                'permission_id' => 93,
                'role_id' => 1,
            ),
            131 => 
            array (
                'permission_id' => 94,
                'role_id' => 1,
            ),
            132 => 
            array (
                'permission_id' => 95,
                'role_id' => 1,
            ),
            133 => 
            array (
                'permission_id' => 96,
                'role_id' => 1,
            ),
            134 => 
            array (
                'permission_id' => 96,
                'role_id' => 3,
            ),
            135 => 
            array (
                'permission_id' => 97,
                'role_id' => 1,
            ),
            136 => 
            array (
                'permission_id' => 97,
                'role_id' => 3,
            ),
            137 => 
            array (
                'permission_id' => 98,
                'role_id' => 1,
            ),
            138 => 
            array (
                'permission_id' => 99,
                'role_id' => 1,
            ),
            139 => 
            array (
                'permission_id' => 100,
                'role_id' => 1,
            ),
            140 => 
            array (
                'permission_id' => 101,
                'role_id' => 1,
            ),
            141 => 
            array (
                'permission_id' => 101,
                'role_id' => 3,
            ),
            142 => 
            array (
                'permission_id' => 102,
                'role_id' => 1,
            ),
            143 => 
            array (
                'permission_id' => 102,
                'role_id' => 3,
            ),
            144 => 
            array (
                'permission_id' => 103,
                'role_id' => 1,
            ),
            145 => 
            array (
                'permission_id' => 104,
                'role_id' => 1,
            ),
            146 => 
            array (
                'permission_id' => 105,
                'role_id' => 1,
            ),
            147 => 
            array (
                'permission_id' => 106,
                'role_id' => 1,
            ),
            148 => 
            array (
                'permission_id' => 106,
                'role_id' => 3,
            ),
            149 => 
            array (
                'permission_id' => 107,
                'role_id' => 1,
            ),
            150 => 
            array (
                'permission_id' => 107,
                'role_id' => 3,
            ),
            151 => 
            array (
                'permission_id' => 108,
                'role_id' => 1,
            ),
            152 => 
            array (
                'permission_id' => 109,
                'role_id' => 1,
            ),
            153 => 
            array (
                'permission_id' => 110,
                'role_id' => 1,
            ),
            154 => 
            array (
                'permission_id' => 111,
                'role_id' => 1,
            ),
            155 => 
            array (
                'permission_id' => 111,
                'role_id' => 3,
            ),
            156 => 
            array (
                'permission_id' => 112,
                'role_id' => 1,
            ),
            157 => 
            array (
                'permission_id' => 112,
                'role_id' => 3,
            ),
            158 => 
            array (
                'permission_id' => 113,
                'role_id' => 1,
            ),
            159 => 
            array (
                'permission_id' => 114,
                'role_id' => 1,
            ),
            160 => 
            array (
                'permission_id' => 115,
                'role_id' => 1,
            ),
            161 => 
            array (
                'permission_id' => 116,
                'role_id' => 1,
            ),
            162 => 
            array (
                'permission_id' => 116,
                'role_id' => 3,
            ),
            163 => 
            array (
                'permission_id' => 117,
                'role_id' => 1,
            ),
            164 => 
            array (
                'permission_id' => 117,
                'role_id' => 3,
            ),
            165 => 
            array (
                'permission_id' => 118,
                'role_id' => 1,
            ),
            166 => 
            array (
                'permission_id' => 119,
                'role_id' => 1,
            ),
            167 => 
            array (
                'permission_id' => 120,
                'role_id' => 1,
            ),
            168 => 
            array (
                'permission_id' => 121,
                'role_id' => 1,
            ),
            169 => 
            array (
                'permission_id' => 121,
                'role_id' => 3,
            ),
            170 => 
            array (
                'permission_id' => 122,
                'role_id' => 1,
            ),
            171 => 
            array (
                'permission_id' => 122,
                'role_id' => 3,
            ),
            172 => 
            array (
                'permission_id' => 123,
                'role_id' => 1,
            ),
            173 => 
            array (
                'permission_id' => 124,
                'role_id' => 1,
            ),
            174 => 
            array (
                'permission_id' => 125,
                'role_id' => 1,
            ),
            175 => 
            array (
                'permission_id' => 126,
                'role_id' => 1,
            ),
            176 => 
            array (
                'permission_id' => 126,
                'role_id' => 3,
            ),
            177 => 
            array (
                'permission_id' => 127,
                'role_id' => 1,
            ),
            178 => 
            array (
                'permission_id' => 127,
                'role_id' => 3,
            ),
            179 => 
            array (
                'permission_id' => 128,
                'role_id' => 1,
            ),
            180 => 
            array (
                'permission_id' => 129,
                'role_id' => 1,
            ),
            181 => 
            array (
                'permission_id' => 130,
                'role_id' => 1,
            ),
            182 => 
            array (
                'permission_id' => 131,
                'role_id' => 1,
            ),
            183 => 
            array (
                'permission_id' => 131,
                'role_id' => 3,
            ),
            184 => 
            array (
                'permission_id' => 132,
                'role_id' => 1,
            ),
            185 => 
            array (
                'permission_id' => 132,
                'role_id' => 3,
            ),
            186 => 
            array (
                'permission_id' => 133,
                'role_id' => 1,
            ),
            187 => 
            array (
                'permission_id' => 134,
                'role_id' => 1,
            ),
            188 => 
            array (
                'permission_id' => 135,
                'role_id' => 1,
            ),
            189 => 
            array (
                'permission_id' => 136,
                'role_id' => 1,
            ),
            190 => 
            array (
                'permission_id' => 136,
                'role_id' => 3,
            ),
            191 => 
            array (
                'permission_id' => 137,
                'role_id' => 1,
            ),
            192 => 
            array (
                'permission_id' => 137,
                'role_id' => 3,
            ),
            193 => 
            array (
                'permission_id' => 138,
                'role_id' => 1,
            ),
            194 => 
            array (
                'permission_id' => 139,
                'role_id' => 1,
            ),
            195 => 
            array (
                'permission_id' => 140,
                'role_id' => 1,
            ),
            196 => 
            array (
                'permission_id' => 141,
                'role_id' => 1,
            ),
            197 => 
            array (
                'permission_id' => 141,
                'role_id' => 3,
            ),
            198 => 
            array (
                'permission_id' => 142,
                'role_id' => 1,
            ),
            199 => 
            array (
                'permission_id' => 142,
                'role_id' => 3,
            ),
            200 => 
            array (
                'permission_id' => 143,
                'role_id' => 1,
            ),
            201 => 
            array (
                'permission_id' => 144,
                'role_id' => 1,
            ),
            202 => 
            array (
                'permission_id' => 145,
                'role_id' => 1,
            ),
            203 => 
            array (
                'permission_id' => 146,
                'role_id' => 1,
            ),
            204 => 
            array (
                'permission_id' => 146,
                'role_id' => 3,
            ),
            205 => 
            array (
                'permission_id' => 147,
                'role_id' => 1,
            ),
            206 => 
            array (
                'permission_id' => 147,
                'role_id' => 3,
            ),
            207 => 
            array (
                'permission_id' => 148,
                'role_id' => 1,
            ),
            208 => 
            array (
                'permission_id' => 149,
                'role_id' => 1,
            ),
            209 => 
            array (
                'permission_id' => 150,
                'role_id' => 1,
            ),
            210 => 
            array (
                'permission_id' => 151,
                'role_id' => 1,
            ),
            211 => 
            array (
                'permission_id' => 151,
                'role_id' => 3,
            ),
            212 => 
            array (
                'permission_id' => 152,
                'role_id' => 1,
            ),
            213 => 
            array (
                'permission_id' => 152,
                'role_id' => 3,
            ),
            214 => 
            array (
                'permission_id' => 153,
                'role_id' => 1,
            ),
            215 => 
            array (
                'permission_id' => 154,
                'role_id' => 1,
            ),
            216 => 
            array (
                'permission_id' => 155,
                'role_id' => 1,
            ),
            217 => 
            array (
                'permission_id' => 157,
                'role_id' => 1,
            ),
            218 => 
            array (
                'permission_id' => 157,
                'role_id' => 3,
            ),
            219 => 
            array (
                'permission_id' => 158,
                'role_id' => 1,
            ),
            220 => 
            array (
                'permission_id' => 158,
                'role_id' => 3,
            ),
            221 => 
            array (
                'permission_id' => 160,
                'role_id' => 1,
            ),
            222 => 
            array (
                'permission_id' => 160,
                'role_id' => 3,
            ),
            223 => 
            array (
                'permission_id' => 161,
                'role_id' => 1,
            ),
            224 => 
            array (
                'permission_id' => 161,
                'role_id' => 3,
            ),
            225 => 
            array (
                'permission_id' => 162,
                'role_id' => 1,
            ),
            226 => 
            array (
                'permission_id' => 162,
                'role_id' => 3,
            ),
            227 => 
            array (
                'permission_id' => 163,
                'role_id' => 1,
            ),
            228 => 
            array (
                'permission_id' => 163,
                'role_id' => 3,
            ),
            229 => 
            array (
                'permission_id' => 164,
                'role_id' => 1,
            ),
            230 => 
            array (
                'permission_id' => 164,
                'role_id' => 3,
            ),
            231 => 
            array (
                'permission_id' => 165,
                'role_id' => 1,
            ),
            232 => 
            array (
                'permission_id' => 165,
                'role_id' => 3,
            ),
            233 => 
            array (
                'permission_id' => 166,
                'role_id' => 1,
            ),
            234 => 
            array (
                'permission_id' => 166,
                'role_id' => 3,
            ),
            235 => 
            array (
                'permission_id' => 167,
                'role_id' => 1,
            ),
            236 => 
            array (
                'permission_id' => 167,
                'role_id' => 3,
            ),
            237 => 
            array (
                'permission_id' => 168,
                'role_id' => 1,
            ),
            238 => 
            array (
                'permission_id' => 168,
                'role_id' => 3,
            ),
            239 => 
            array (
                'permission_id' => 170,
                'role_id' => 1,
            ),
            240 => 
            array (
                'permission_id' => 170,
                'role_id' => 3,
            ),
            241 => 
            array (
                'permission_id' => 172,
                'role_id' => 1,
            ),
            242 => 
            array (
                'permission_id' => 172,
                'role_id' => 3,
            ),
            243 => 
            array (
                'permission_id' => 173,
                'role_id' => 1,
            ),
            244 => 
            array (
                'permission_id' => 173,
                'role_id' => 3,
            ),
            245 => 
            array (
                'permission_id' => 174,
                'role_id' => 1,
            ),
            246 => 
            array (
                'permission_id' => 174,
                'role_id' => 3,
            ),
            247 => 
            array (
                'permission_id' => 175,
                'role_id' => 1,
            ),
            248 => 
            array (
                'permission_id' => 175,
                'role_id' => 3,
            ),
            249 => 
            array (
                'permission_id' => 176,
                'role_id' => 1,
            ),
            250 => 
            array (
                'permission_id' => 176,
                'role_id' => 3,
            ),
            251 => 
            array (
                'permission_id' => 177,
                'role_id' => 1,
            ),
            252 => 
            array (
                'permission_id' => 177,
                'role_id' => 3,
            ),
            253 => 
            array (
                'permission_id' => 178,
                'role_id' => 1,
            ),
            254 => 
            array (
                'permission_id' => 178,
                'role_id' => 3,
            ),
            255 => 
            array (
                'permission_id' => 179,
                'role_id' => 1,
            ),
            256 => 
            array (
                'permission_id' => 179,
                'role_id' => 3,
            ),
            257 => 
            array (
                'permission_id' => 180,
                'role_id' => 1,
            ),
            258 => 
            array (
                'permission_id' => 180,
                'role_id' => 3,
            ),
            259 => 
            array (
                'permission_id' => 181,
                'role_id' => 1,
            ),
            260 => 
            array (
                'permission_id' => 181,
                'role_id' => 3,
            ),
            261 => 
            array (
                'permission_id' => 182,
                'role_id' => 1,
            ),
            262 => 
            array (
                'permission_id' => 183,
                'role_id' => 1,
            ),
            263 => 
            array (
                'permission_id' => 184,
                'role_id' => 1,
            ),
            264 => 
            array (
                'permission_id' => 185,
                'role_id' => 1,
            ),
            265 => 
            array (
                'permission_id' => 185,
                'role_id' => 3,
            ),
            266 => 
            array (
                'permission_id' => 186,
                'role_id' => 1,
            ),
            267 => 
            array (
                'permission_id' => 186,
                'role_id' => 3,
            ),
            268 => 
            array (
                'permission_id' => 187,
                'role_id' => 1,
            ),
            269 => 
            array (
                'permission_id' => 188,
                'role_id' => 1,
            ),
            270 => 
            array (
                'permission_id' => 189,
                'role_id' => 1,
            ),
            271 => 
            array (
                'permission_id' => 190,
                'role_id' => 1,
            ),
            272 => 
            array (
                'permission_id' => 190,
                'role_id' => 3,
            ),
            273 => 
            array (
                'permission_id' => 191,
                'role_id' => 1,
            ),
            274 => 
            array (
                'permission_id' => 191,
                'role_id' => 3,
            ),
            275 => 
            array (
                'permission_id' => 192,
                'role_id' => 1,
            ),
            276 => 
            array (
                'permission_id' => 192,
                'role_id' => 3,
            ),
            277 => 
            array (
                'permission_id' => 193,
                'role_id' => 1,
            ),
            278 => 
            array (
                'permission_id' => 194,
                'role_id' => 1,
            ),
            279 => 
            array (
                'permission_id' => 195,
                'role_id' => 1,
            ),
            280 =>
            array (
                'permission_id' => 196,
                'role_id' => 1,
            ),
            281 =>
            array (
                'permission_id' => 196,
                'role_id' => 3,
            ),
            282 =>
            array (
                'permission_id' => 198,
                'role_id' => 1,
            ),
            283 =>
            array (
                'permission_id' => 198,
                'role_id' => 3,
            ),
            284 =>
            array (
                'permission_id' => 199,
                'role_id' => 1,
            ),
            285 =>
            array (
                'permission_id' => 199,
                'role_id' => 3,
            ),
            286 =>
            array (
                'permission_id' => 200,
                'role_id' => 1,
            ),
            287 =>
            array (
                'permission_id' => 201,
                'role_id' => 1,
            ),
            288 =>
            array (
                'permission_id' => 202,
                'role_id' => 1,
            ),
            289 =>
            array (
                'permission_id' => 197,
                'role_id' => 1,
            ),
            290 =>
            array (
                'permission_id' => 197,
                'role_id' => 3,
            ),
            291 =>
            array (
                'permission_id' => 203,
                'role_id' => 1,
            ),
            292 =>
            array (
                'permission_id' => 203,
                'role_id' => 3,
            ),
            293 =>
            array (
                'permission_id' => 204,
                'role_id' => 1,
            ),
            294 =>
            array (
                'permission_id' => 204,
                'role_id' => 3,
            ),
            295 =>
            array (
                'permission_id' => 205,
                'role_id' => 1,
            ),
            296 =>
            array (
                'permission_id' => 206,
                'role_id' => 1,
            ),
            297 =>
            array (
                'permission_id' => 207,
                'role_id' => 1,
            ),
        ));
        
        
    }
}
