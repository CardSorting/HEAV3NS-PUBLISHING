<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('permissions')->delete();
        
        \DB::table('permissions')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'ViewAny:Attachment',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'View:Attachment',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Create:Attachment',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Update:Attachment',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Delete:Attachment',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'ViewAny:ContactMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'View:ContactMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'Create:ContactMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'Update:ContactMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            9 => 
            array (
                'id' => 10,
                'name' => 'Delete:ContactMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            10 => 
            array (
                'id' => 11,
                'name' => 'ViewAny:Country',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            11 => 
            array (
                'id' => 12,
                'name' => 'View:Country',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            12 => 
            array (
                'id' => 13,
                'name' => 'Create:Country',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            13 => 
            array (
                'id' => 14,
                'name' => 'Update:Country',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            14 => 
            array (
                'id' => 15,
                'name' => 'Delete:Country',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            15 => 
            array (
                'id' => 16,
                'name' => 'ViewAny:FeaturedUser',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            16 => 
            array (
                'id' => 17,
                'name' => 'View:FeaturedUser',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            17 => 
            array (
                'id' => 18,
                'name' => 'Create:FeaturedUser',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            18 => 
            array (
                'id' => 19,
                'name' => 'Update:FeaturedUser',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            19 => 
            array (
                'id' => 20,
                'name' => 'Delete:FeaturedUser',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            20 => 
            array (
                'id' => 21,
                'name' => 'ViewAny:GlobalAnnouncement',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            21 => 
            array (
                'id' => 22,
                'name' => 'View:GlobalAnnouncement',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            22 => 
            array (
                'id' => 23,
                'name' => 'Create:GlobalAnnouncement',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            23 => 
            array (
                'id' => 24,
                'name' => 'Update:GlobalAnnouncement',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            24 => 
            array (
                'id' => 25,
                'name' => 'Delete:GlobalAnnouncement',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            25 => 
            array (
                'id' => 26,
                'name' => 'ViewAny:Invoice',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            26 => 
            array (
                'id' => 27,
                'name' => 'View:Invoice',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            27 => 
            array (
                'id' => 28,
                'name' => 'Create:Invoice',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            28 => 
            array (
                'id' => 29,
                'name' => 'Update:Invoice',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            29 => 
            array (
                'id' => 30,
                'name' => 'Delete:Invoice',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            30 => 
            array (
                'id' => 31,
                'name' => 'ViewAny:Notification',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            31 => 
            array (
                'id' => 32,
                'name' => 'View:Notification',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            32 => 
            array (
                'id' => 33,
                'name' => 'Create:Notification',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            33 => 
            array (
                'id' => 34,
                'name' => 'Update:Notification',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            34 => 
            array (
                'id' => 35,
                'name' => 'Delete:Notification',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            35 => 
            array (
                'id' => 36,
                'name' => 'ViewAny:PaymentRequest',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            36 => 
            array (
                'id' => 37,
                'name' => 'View:PaymentRequest',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            37 => 
            array (
                'id' => 38,
                'name' => 'Create:PaymentRequest',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            38 => 
            array (
                'id' => 39,
                'name' => 'Update:PaymentRequest',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            39 => 
            array (
                'id' => 40,
                'name' => 'Delete:PaymentRequest',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            40 => 
            array (
                'id' => 41,
                'name' => 'ViewAny:PollAnswer',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            41 => 
            array (
                'id' => 42,
                'name' => 'View:PollAnswer',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            42 => 
            array (
                'id' => 43,
                'name' => 'Create:PollAnswer',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            43 => 
            array (
                'id' => 44,
                'name' => 'Update:PollAnswer',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            44 => 
            array (
                'id' => 45,
                'name' => 'Delete:PollAnswer',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            45 => 
            array (
                'id' => 46,
                'name' => 'ViewAny:PollUserAnswer',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            46 => 
            array (
                'id' => 47,
                'name' => 'View:PollUserAnswer',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            47 => 
            array (
                'id' => 48,
                'name' => 'Create:PollUserAnswer',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            48 => 
            array (
                'id' => 49,
                'name' => 'Update:PollUserAnswer',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            49 => 
            array (
                'id' => 50,
                'name' => 'Delete:PollUserAnswer',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            50 => 
            array (
                'id' => 51,
                'name' => 'ViewAny:Poll',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            51 => 
            array (
                'id' => 52,
                'name' => 'View:Poll',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            52 => 
            array (
                'id' => 53,
                'name' => 'Create:Poll',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            53 => 
            array (
                'id' => 54,
                'name' => 'Update:Poll',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            54 => 
            array (
                'id' => 55,
                'name' => 'Delete:Poll',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            55 => 
            array (
                'id' => 56,
                'name' => 'ViewAny:PostComment',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            56 => 
            array (
                'id' => 57,
                'name' => 'View:PostComment',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            57 => 
            array (
                'id' => 58,
                'name' => 'Create:PostComment',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            58 => 
            array (
                'id' => 59,
                'name' => 'Update:PostComment',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            59 => 
            array (
                'id' => 60,
                'name' => 'Delete:PostComment',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            60 => 
            array (
                'id' => 61,
                'name' => 'ViewAny:Post',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            61 => 
            array (
                'id' => 62,
                'name' => 'View:Post',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            62 => 
            array (
                'id' => 63,
                'name' => 'Create:Post',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            63 => 
            array (
                'id' => 64,
                'name' => 'Update:Post',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            64 => 
            array (
                'id' => 65,
                'name' => 'Delete:Post',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            65 => 
            array (
                'id' => 66,
                'name' => 'ViewAny:PublicPage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            66 => 
            array (
                'id' => 67,
                'name' => 'View:PublicPage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            67 => 
            array (
                'id' => 68,
                'name' => 'Create:PublicPage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            68 => 
            array (
                'id' => 69,
                'name' => 'Update:PublicPage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            69 => 
            array (
                'id' => 70,
                'name' => 'Delete:PublicPage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            70 => 
            array (
                'id' => 71,
                'name' => 'ViewAny:Reaction',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            71 => 
            array (
                'id' => 72,
                'name' => 'View:Reaction',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            72 => 
            array (
                'id' => 73,
                'name' => 'Create:Reaction',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            73 => 
            array (
                'id' => 74,
                'name' => 'Update:Reaction',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            74 => 
            array (
                'id' => 75,
                'name' => 'Delete:Reaction',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            75 => 
            array (
                'id' => 76,
                'name' => 'ViewAny:Reward',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            76 => 
            array (
                'id' => 77,
                'name' => 'View:Reward',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            77 => 
            array (
                'id' => 78,
                'name' => 'Create:Reward',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            78 => 
            array (
                'id' => 79,
                'name' => 'Update:Reward',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            79 => 
            array (
                'id' => 80,
                'name' => 'Delete:Reward',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            80 => 
            array (
                'id' => 81,
                'name' => 'ViewAny:Role',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            81 => 
            array (
                'id' => 82,
                'name' => 'View:Role',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            82 => 
            array (
                'id' => 83,
                'name' => 'Create:Role',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            83 => 
            array (
                'id' => 84,
                'name' => 'Update:Role',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            84 => 
            array (
                'id' => 85,
                'name' => 'Delete:Role',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            85 => 
            array (
                'id' => 86,
                'name' => 'ViewAny:StreamMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            86 => 
            array (
                'id' => 87,
                'name' => 'View:StreamMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            87 => 
            array (
                'id' => 88,
                'name' => 'Create:StreamMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            88 => 
            array (
                'id' => 89,
                'name' => 'Update:StreamMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            89 => 
            array (
                'id' => 90,
                'name' => 'Delete:StreamMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            90 => 
            array (
                'id' => 91,
                'name' => 'ViewAny:Stream',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            91 => 
            array (
                'id' => 92,
                'name' => 'View:Stream',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            92 => 
            array (
                'id' => 93,
                'name' => 'Create:Stream',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            93 => 
            array (
                'id' => 94,
                'name' => 'Update:Stream',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            94 => 
            array (
                'id' => 95,
                'name' => 'Delete:Stream',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            95 => 
            array (
                'id' => 96,
                'name' => 'ViewAny:Subscription',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            96 => 
            array (
                'id' => 97,
                'name' => 'View:Subscription',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            97 => 
            array (
                'id' => 98,
                'name' => 'Create:Subscription',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            98 => 
            array (
                'id' => 99,
                'name' => 'Update:Subscription',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            99 => 
            array (
                'id' => 100,
                'name' => 'Delete:Subscription',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            100 => 
            array (
                'id' => 101,
                'name' => 'ViewAny:Tax',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            101 => 
            array (
                'id' => 102,
                'name' => 'View:Tax',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            102 => 
            array (
                'id' => 103,
                'name' => 'Create:Tax',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            103 => 
            array (
                'id' => 104,
                'name' => 'Update:Tax',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            104 => 
            array (
                'id' => 105,
                'name' => 'Delete:Tax',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            105 => 
            array (
                'id' => 106,
                'name' => 'ViewAny:Transaction',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            106 => 
            array (
                'id' => 107,
                'name' => 'View:Transaction',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            107 => 
            array (
                'id' => 108,
                'name' => 'Create:Transaction',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            108 => 
            array (
                'id' => 109,
                'name' => 'Update:Transaction',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            109 => 
            array (
                'id' => 110,
                'name' => 'Delete:Transaction',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            110 => 
            array (
                'id' => 111,
                'name' => 'ViewAny:UserBookmark',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            111 => 
            array (
                'id' => 112,
                'name' => 'View:UserBookmark',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            112 => 
            array (
                'id' => 113,
                'name' => 'Create:UserBookmark',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            113 => 
            array (
                'id' => 114,
                'name' => 'Update:UserBookmark',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            114 => 
            array (
                'id' => 115,
                'name' => 'Delete:UserBookmark',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            115 => 
            array (
                'id' => 116,
                'name' => 'ViewAny:UserList',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            116 => 
            array (
                'id' => 117,
                'name' => 'View:UserList',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            117 => 
            array (
                'id' => 118,
                'name' => 'Create:UserList',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            118 => 
            array (
                'id' => 119,
                'name' => 'Update:UserList',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            119 => 
            array (
                'id' => 120,
                'name' => 'Delete:UserList',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            120 => 
            array (
                'id' => 121,
                'name' => 'ViewAny:UserMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            121 => 
            array (
                'id' => 122,
                'name' => 'View:UserMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            122 => 
            array (
                'id' => 123,
                'name' => 'Create:UserMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            123 => 
            array (
                'id' => 124,
                'name' => 'Update:UserMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            124 => 
            array (
                'id' => 125,
                'name' => 'Delete:UserMessage',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            125 => 
            array (
                'id' => 126,
                'name' => 'ViewAny:UserReport',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            126 => 
            array (
                'id' => 127,
                'name' => 'View:UserReport',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            127 => 
            array (
                'id' => 128,
                'name' => 'Create:UserReport',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            128 => 
            array (
                'id' => 129,
                'name' => 'Update:UserReport',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            129 => 
            array (
                'id' => 130,
                'name' => 'Delete:UserReport',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            130 => 
            array (
                'id' => 131,
                'name' => 'ViewAny:UserTax',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            131 => 
            array (
                'id' => 132,
                'name' => 'View:UserTax',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            132 => 
            array (
                'id' => 133,
                'name' => 'Create:UserTax',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            133 => 
            array (
                'id' => 134,
                'name' => 'Update:UserTax',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            134 => 
            array (
                'id' => 135,
                'name' => 'Delete:UserTax',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            135 => 
            array (
                'id' => 136,
                'name' => 'ViewAny:UserVerify',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            136 => 
            array (
                'id' => 137,
                'name' => 'View:UserVerify',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            137 => 
            array (
                'id' => 138,
                'name' => 'Create:UserVerify',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            138 => 
            array (
                'id' => 139,
                'name' => 'Update:UserVerify',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            139 => 
            array (
                'id' => 140,
                'name' => 'Delete:UserVerify',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            140 => 
            array (
                'id' => 141,
                'name' => 'ViewAny:User',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            141 => 
            array (
                'id' => 142,
                'name' => 'View:User',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            142 => 
            array (
                'id' => 143,
                'name' => 'Create:User',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            143 => 
            array (
                'id' => 144,
                'name' => 'Update:User',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            144 => 
            array (
                'id' => 145,
                'name' => 'Delete:User',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            145 => 
            array (
                'id' => 146,
                'name' => 'ViewAny:Wallet',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            146 => 
            array (
                'id' => 147,
                'name' => 'View:Wallet',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            147 => 
            array (
                'id' => 148,
                'name' => 'Create:Wallet',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            148 => 
            array (
                'id' => 149,
                'name' => 'Update:Wallet',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            149 => 
            array (
                'id' => 150,
                'name' => 'Delete:Wallet',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            150 => 
            array (
                'id' => 151,
                'name' => 'ViewAny:Withdrawal',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            151 => 
            array (
                'id' => 152,
                'name' => 'View:Withdrawal',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            152 => 
            array (
                'id' => 153,
                'name' => 'Create:Withdrawal',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            153 => 
            array (
                'id' => 154,
                'name' => 'Update:Withdrawal',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            154 => 
            array (
                'id' => 155,
                'name' => 'Delete:Withdrawal',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            155 => 
            array (
                'id' => 156,
                'name' => 'View:Dashboard',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            156 => 
            array (
                'id' => 157,
                'name' => 'View:ManageAdminSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            157 => 
            array (
                'id' => 158,
                'name' => 'View:ManageAiSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            158 => 
            array (
                'id' => 159,
                'name' => 'View:ManageCodeAndAdsSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            159 => 
            array (
                'id' => 160,
                'name' => 'View:ManageColorsSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            160 => 
            array (
                'id' => 161,
                'name' => 'View:ManageComplianceSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            161 => 
            array (
                'id' => 162,
                'name' => 'View:ManageEmailsSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            162 => 
            array (
                'id' => 163,
                'name' => 'View:ManageFeedSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            163 => 
            array (
                'id' => 164,
                'name' => 'View:ManageGeneralSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            164 => 
            array (
                'id' => 165,
                'name' => 'View:ManageLicenseSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            165 => 
            array (
                'id' => 166,
                'name' => 'View:ManageMediaSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            166 => 
            array (
                'id' => 167,
                'name' => 'View:ManagePaymentsSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            167 => 
            array (
                'id' => 168,
                'name' => 'View:ManageProfilesSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            168 => 
            array (
                'id' => 169,
                'name' => 'View:ManageReferralSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            169 => 
            array (
                'id' => 170,
                'name' => 'View:ManageSecuritySettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            170 => 
            array (
                'id' => 171,
                'name' => 'View:ManageSocialSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            171 => 
            array (
                'id' => 172,
                'name' => 'View:ManageStorageSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            172 => 
            array (
                'id' => 173,
                'name' => 'View:ManageStreamsSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            173 => 
            array (
                'id' => 174,
                'name' => 'View:ManageWebsocketsSettings',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            174 => 
            array (
                'id' => 175,
                'name' => 'View:ViewLog',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            175 => 
            array (
                'id' => 176,
                'name' => 'View:UsersChart',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            176 => 
            array (
                'id' => 177,
                'name' => 'View:PostsChart',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            177 => 
            array (
                'id' => 178,
                'name' => 'View:TransactionsChart',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            178 => 
            array (
                'id' => 179,
                'name' => 'View:StreamsChart',
                'guard_name' => 'web',
                'created_at' => '2025-10-25 13:56:29',
                'updated_at' => '2025-10-25 13:56:29',
            ),
            179 => 
            array (
                'id' => 180,
                'name' => 'ViewAny:Sound',
                'guard_name' => 'web',
                'created_at' => '2026-01-03 18:50:46',
                'updated_at' => '2026-01-03 18:50:46',
            ),
            180 => 
            array (
                'id' => 181,
                'name' => 'View:Sound',
                'guard_name' => 'web',
                'created_at' => '2026-01-03 18:50:46',
                'updated_at' => '2026-01-03 18:50:46',
            ),
            181 => 
            array (
                'id' => 182,
                'name' => 'Create:Sound',
                'guard_name' => 'web',
                'created_at' => '2026-01-03 18:50:46',
                'updated_at' => '2026-01-03 18:50:46',
            ),
            182 => 
            array (
                'id' => 183,
                'name' => 'Update:Sound',
                'guard_name' => 'web',
                'created_at' => '2026-01-03 18:50:46',
                'updated_at' => '2026-01-03 18:50:46',
            ),
            183 => 
            array (
                'id' => 184,
                'name' => 'Delete:Sound',
                'guard_name' => 'web',
                'created_at' => '2026-01-03 18:50:46',
                'updated_at' => '2026-01-03 18:50:46',
            ),
            184 => 
            array (
                'id' => 185,
                'name' => 'ViewAny:Story',
                'guard_name' => 'web',
                'created_at' => '2026-01-03 18:50:46',
                'updated_at' => '2026-01-03 18:50:46',
            ),
            185 => 
            array (
                'id' => 186,
                'name' => 'View:Story',
                'guard_name' => 'web',
                'created_at' => '2026-01-03 18:50:46',
                'updated_at' => '2026-01-03 18:50:46',
            ),
            186 => 
            array (
                'id' => 187,
                'name' => 'Create:Story',
                'guard_name' => 'web',
                'created_at' => '2026-01-03 18:50:46',
                'updated_at' => '2026-01-03 18:50:46',
            ),
            187 => 
            array (
                'id' => 188,
                'name' => 'Update:Story',
                'guard_name' => 'web',
                'created_at' => '2026-01-03 18:50:46',
                'updated_at' => '2026-01-03 18:50:46',
            ),
            188 => 
            array (
                'id' => 189,
                'name' => 'Delete:Story',
                'guard_name' => 'web',
                'created_at' => '2026-01-03 18:50:46',
                'updated_at' => '2026-01-03 18:50:46',
            ),
            189 => 
            array (
                'id' => 190,
                'name' => 'View:ManageStoriesSettings',
                'guard_name' => 'web',
                'created_at' => '2026-01-04 16:51:54',
                'updated_at' => '2026-01-04 16:51:54',
            ),
            190 => 
            array (
                'id' => 191,
                'name' => 'ViewAny:Hashtag',
                'guard_name' => 'web',
                'created_at' => '2026-02-04 13:01:02',
                'updated_at' => '2026-02-04 13:01:02',
            ),
            191 => 
            array (
                'id' => 192,
                'name' => 'View:Hashtag',
                'guard_name' => 'web',
                'created_at' => '2026-02-04 13:01:02',
                'updated_at' => '2026-02-04 13:01:02',
            ),
            192 => 
            array (
                'id' => 193,
                'name' => 'Create:Hashtag',
                'guard_name' => 'web',
                'created_at' => '2026-02-04 13:01:02',
                'updated_at' => '2026-02-04 13:01:02',
            ),
            193 => 
            array (
                'id' => 194,
                'name' => 'Update:Hashtag',
                'guard_name' => 'web',
                'created_at' => '2026-02-04 13:01:02',
                'updated_at' => '2026-02-04 13:01:02',
            ),
            194 => 
            array (
                'id' => 195,
                'name' => 'Delete:Hashtag',
                'guard_name' => 'web',
                'created_at' => '2026-02-04 13:01:02',
                'updated_at' => '2026-02-04 13:01:02',
            ),
            195 =>
            array (
                'id' => 196,
                'name' => 'View:ManageRuntimeSettings',
                'guard_name' => 'web',
                'created_at' => '2026-04-27 00:00:00',
                'updated_at' => '2026-04-27 00:00:00',
            ),
            196 =>
            array (
                'id' => 197,
                'name' => 'View:ManageReelsSettings',
                'guard_name' => 'web',
                'created_at' => '2026-05-02 00:00:00',
                'updated_at' => '2026-05-02 00:00:00',
            ),
            197 =>
            array (
                'id' => 198,
                'name' => 'ViewAny:Reel',
                'guard_name' => 'web',
                'created_at' => '2026-05-10 00:00:00',
                'updated_at' => '2026-05-10 00:00:00',
            ),
            198 =>
            array (
                'id' => 199,
                'name' => 'View:Reel',
                'guard_name' => 'web',
                'created_at' => '2026-05-10 00:00:00',
                'updated_at' => '2026-05-10 00:00:00',
            ),
            199 =>
            array (
                'id' => 200,
                'name' => 'Create:Reel',
                'guard_name' => 'web',
                'created_at' => '2026-05-10 00:00:00',
                'updated_at' => '2026-05-10 00:00:00',
            ),
            200 =>
            array (
                'id' => 201,
                'name' => 'Update:Reel',
                'guard_name' => 'web',
                'created_at' => '2026-05-10 00:00:00',
                'updated_at' => '2026-05-10 00:00:00',
            ),
            201 =>
            array (
                'id' => 202,
                'name' => 'Delete:Reel',
                'guard_name' => 'web',
                'created_at' => '2026-05-10 00:00:00',
                'updated_at' => '2026-05-10 00:00:00',
            ),
            202 =>
            array (
                'id' => 203,
                'name' => 'ViewAny:ReleaseForm',
                'guard_name' => 'web',
                'created_at' => '2026-05-23 00:00:00',
                'updated_at' => '2026-05-23 00:00:00',
            ),
            203 =>
            array (
                'id' => 204,
                'name' => 'View:ReleaseForm',
                'guard_name' => 'web',
                'created_at' => '2026-05-23 00:00:00',
                'updated_at' => '2026-05-23 00:00:00',
            ),
            204 =>
            array (
                'id' => 205,
                'name' => 'Create:ReleaseForm',
                'guard_name' => 'web',
                'created_at' => '2026-05-23 00:00:00',
                'updated_at' => '2026-05-23 00:00:00',
            ),
            205 =>
            array (
                'id' => 206,
                'name' => 'Update:ReleaseForm',
                'guard_name' => 'web',
                'created_at' => '2026-05-23 00:00:00',
                'updated_at' => '2026-05-23 00:00:00',
            ),
            206 =>
            array (
                'id' => 207,
                'name' => 'Delete:ReleaseForm',
                'guard_name' => 'web',
                'created_at' => '2026-05-23 00:00:00',
                'updated_at' => '2026-05-23 00:00:00',
            ),
        ));
        
        
    }
}
