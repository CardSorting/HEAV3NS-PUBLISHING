<?php

return [
    'dashboard' => [

    ],

    'common' => [
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
        'expiring_at' => 'Expiring at',
        'canceled_at' => 'Canceled at',
        'create' => 'Create',
        'edit' => 'Update',
        'delete' => 'Delete',
        'view' => 'View',
        'id' => 'ID',
        'files' => 'Files',
    ],

    'navigation' => [
        'dashboard' => 'Dashboard',
        'groups' => [
            'users' => 'Users',
            'moderation' => 'Moderation',
            'content' => 'Content',
            'posts' => 'Posts',
            'finances' => 'Finances',
            'taxes' => 'Taxes',
            'stories' => 'Short-form',
            'streams' => 'Streams',
            'site' => 'Site',
            'settings' => 'Settings',
        ],
    ],

    'filters' => [
        'title' => 'Filters',
        'start_date' => 'Start date',
        'end_date' => 'End date',
        'today' => 'Today',
        'week' => 'Last week',
        'month' => 'Last month',
        'year' => 'This year',
        'last_month' => 'Last 30 days',
        'last_year' => 'Last 12 months',

    ],

    'widgets' => [
        'stats_overview' => [
            'title' => 'Last 7 days overview',

            'revenue' => [
                'label' => 'Revenue',
                'description' => 'Total revenue earned',
            ],
            'new_users' => [
                'label' => 'New users',
                'description' => 'Users registered',
            ],
            'new_payments' => [
                'label' => 'Payments',
                'description' => 'Transactions completed',
            ],
        ],

        'users_chart' => [
            'title' => 'Users',
            'datasets' => [
                'users' => 'Users',
                'user_messages' => 'User messages',
            ],
        ],

        'posts_chart' => [
            'title' => 'Posts',
            'filters' => [
                'today' => 'Today',
                'week' => 'Last week',
                'month' => 'Last month',
                'year' => 'This year',
            ],
            'datasets' => [
                'posts' => 'Posts',
                'comments' => 'Comments',
                'reactions' => 'Reactions',
            ],
        ],

        'transactions_chart' => [
            'title' => 'Payments',
            'filters' => [
                'today' => 'Today',
                'week' => 'Last week',
                'month' => 'Last month',
                'year' => 'This year',
            ],
            'datasets' => [
                'transactions' => 'Payments',
                'subscriptions' => 'Subscriptions',
            ],
        ],

        'streams_chart' => [
            'title' => 'Streams',
            'filters' => [
                'today' => 'Today',
                'week' => 'Last week',
                'month' => 'Last month',
                'year' => 'This year',
            ],
            'datasets' => [
                'streams' => 'Streams',
                'stream_messages' => 'Stream messages',
            ],
        ],

        'product_info' => [
            'title' => 'Quickstart',
            'website' => [
                'title' => 'Website',
                'description' => 'Visit the official product page',
            ],
            'documentation' => [
                'title' => 'Documentation',
                'description' => 'Visit the official product docs',
            ],
            'changelog' => [
                'title' => 'Changelog',
                'description' => 'Visit the official product changelog',
            ],
        ],

        'transaction_stats' => [
            'heading' => 'This year payments',
            'total' => 'Total payments',
            'completed' => 'Completed payments',
            'average' => 'Average price',
        ],

        'subscription_stats' => [
            'heading' => 'This year subscriptions',
            'total' => 'Total subscriptions',
            'active' => 'Currently active subscriptions',
            'average_price' => 'Average price',
        ],

    ],

    'resources' => [
        'user' => [
            'label' => 'User',
            'plural' => 'Users',
            'sections' => [
                'account_info' => 'Account info',
                'paywall_info' => 'Paywall info',
                'profile_info' => 'Profile info',
                'withdrawals_info' => 'Withdrawals info',
                'security_info' => 'Security info',
                'billing_info' => 'Billing info',
            ],
            'fields' => [
                'id' => 'ID',
                'name' => 'Name',
                'email' => 'Email',
                'username' => 'Username',
                'password' => 'Password',
                'roles' => 'Role',
                'email_verified_at' => 'Email verified at',
                'identity_verified_at' => 'ID verified at',
                'birthdate' => 'Birthdate',
                'paid_profile' => 'Paid profile',
                'public_profile' => 'Public profile',
                'open_profile' => 'Open profile',
                'profile_access_price' => 'Access price',
                'profile_access_price_3_months' => '3 months access price',
                'profile_access_price_6_months' => '6 months access price',
                'profile_access_price_12_months' => '12 months access price',
                'current_avatar' => 'Current avatar',
                'avatar' => 'Avatar',
                'current_cover' => 'Current cover',
                'cover' => 'Cover',
                'bio' => 'Bio',
                'location' => 'Location',
                'gender_id' => 'Gender',
                'gender_pronoun' => 'Pronoun',
                'website' => 'Website',
                'referral_code' => 'Referral code',
                'stripe_account_id' => 'Stripe Connect ID',
                'country_id' => 'Stripe Connect country',
                'stripe_onboarding_verified' => 'Stripe onboarding verified',
                'last_ip' => 'Last IP',
                'last_active_at' => 'Last active at',
                'enable_geoblocking' => 'Enable geo-blocking',
                'enable_2fa' => 'Enable 2FA',
                'billing_address' => 'Billing address',
                'first_name' => 'First name',
                'last_name' => 'Last name',
                'city' => 'City',
                'country' => 'Country',
                'state' => 'State',
                'postcode' => 'Postcode',
                'gender' => 'Gender',
            ],
            'actions' => [
                'impersonate' => 'Impersonate',
                'profile_url' => 'Profile URL',
            ],
        ],

        'user_verify' => [
            'label' => 'ID-Check',
            'plural' => 'ID-Checks',

            'sections' => [
                'verification_details' => 'Verification details',
                'verification_details_descr' => 'Manage user verification request.',
            ],

            'tabs' => [
                'all' => 'All',
                'pending' => 'Pending',
                'approved' => 'Approved',
                'rejected' => 'Declined',
            ],

            'fields' => [
                'user_id' => 'User',
                'status' => 'Status',
                'rejectionReason' => 'Rejection reason',
                'files' => 'Files preview'
            ],

            'actions' => [
                'profile_url' => 'Profile URL',
                'approve' => 'Approve',
                'reject' => 'Reject',
            ],

            'navigation_badge_tooltip' => 'The number of pending ID-checks',
        ],

        'release_form' => [
            'label' => 'Release form',
            'plural' => 'Release forms',

            'sections' => [
                'release_form_details' => 'Release form details',
            ],

            'tabs' => [
                'all' => 'All',
                'pending' => 'Pending',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
            ],

            'status_labels' => [
                'pending' => 'Pending',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
            ],

            'fields' => [
                'user_id' => 'Creator',
                'title' => 'Title',
                'status' => 'Status',
                'files' => 'Files',
                'reviewed_by' => 'Reviewed by',
                'reviewed_at' => 'Reviewed at',
                'notes' => 'Creator notes',
                'rejection_reason' => 'Rejection reason',
            ],

            'actions' => [
                'approve' => 'Approve',
                'reject' => 'Reject',
            ],

            'navigation_badge_tooltip' => 'The number of pending release forms',
        ],

        'user_deletion_request' => [
            'label' => 'Deletion request',
            'plural' => 'Deletion requests',

            'sections' => [
                'request_details' => 'Request details',
            ],

            'tabs' => [
                'all' => 'All',
                'pending' => 'Pending',
                'approved' => 'Approved',
                'blocked' => 'Blocked',
                'completed' => 'Completed',
            ],

            'status_labels' => [
                'pending' => 'Pending',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                'canceled' => 'Canceled',
                'completed' => 'Completed',
                'blocked' => 'Blocked',
            ],

            'mode_labels' => [
                'manual_review' => 'Admin approval only',
                'cooldown_auto' => 'Automatic after cooldown',
                'manual_review_with_cooldown' => 'Admin approval plus cooldown',
            ],

            'fields' => [
                'user_id' => 'User',
                'requested_name' => 'Requested name',
                'requested_email' => 'Requested email',
                'requested_username' => 'Requested username',
                'status' => 'Status',
                'mode' => 'Workflow',
                'reason' => 'User reason',
                'admin_notes' => 'Admin notes',
                'rejection_reason' => 'Rejection reason',
                'requested_at' => 'Requested at',
                'eligible_for_deletion_at' => 'Eligible for deletion at',
                'reviewed_by' => 'Reviewed by',
                'reviewed_at' => 'Reviewed at',
                'canceled_at' => 'Canceled at',
                'completed_at' => 'Completed at',
            ],

            'actions' => [
                'approve' => 'Approve deletion',
                'approve_modal_heading' => 'Approve deletion request?',
                'approve_modal_description' => 'This approves the request. Deletion will run only after the configured cooldown and final account checks pass.',
                'approve_modal_description_manual_review' => 'This workflow has no cooldown. If final account checks pass, approving this request deletes the account immediately.',
                'approve_modal_submit' => 'Approve request',
                'reject' => 'Reject',
                'cancel' => 'Cancel request',
            ],

            'navigation_badge_tooltip' => 'The number of pending or blocked account deletion requests',
        ],

        'wallet' => [
            'label' => 'Wallet',
            'plural' => 'Wallets',

            'sections' => [
                'wallet_details' => 'Wallet details',
            ],

            'fields' => [
                'id' => 'Wallet ID',
                'user_id' => 'User',
                'total' => 'Total amount',
                'created_at' => 'Created at',
                'updated_at' => 'Updated at',
            ],

            'helper_texts' => [
                'id' => 'UUID format preferred.',
            ],
        ],

        'notification' => [
            'label' => 'Notification',
            'plural' => 'Notifications',

            'sections' => [
                'general_info' => 'General information',
                'notification_details' => 'Notification details',
                'linked_models' => 'Linked models',
            ],

            'fields' => [
                'id' => 'Notification ID',
                'from_user_id' => 'From user',
                'to_user_id' => 'To user',
                'type' => 'Notification type',
                'read' => 'Mark as read',
                'post_id' => 'Post ID',
                'post_comment_id' => 'Post comment ID',
                'subscription_id' => 'Subscription ID',
                'transaction_id' => 'Transaction ID',
                'reaction_id' => 'Reaction ID',
                'withdrawal_id' => 'Withdrawal ID',
                'user_message_id' => 'User message ID',
                'stream_id' => 'Stream ID'
            ],

            'helper_texts' => [
                'id' => 'UUID format preferred.',
                'read' => 'Indicates whether the user has seen the notification.',
            ],

            'types' => [
                'ppv_unlock' => 'Content unlocked',
                'expiring_stream' => 'Expiring stream',
                'new_message' => 'New message',
                'withdrawal_action' => 'Withdrawal update',
                'new_subscription' => 'New subscription',
                'new_comment' => 'New comment',
                'comment_reply' => 'Comment reply',
                'new_reaction' => 'New reaction',
                'new_tip' => 'New tip',
                'mention' => 'Mention',
            ],
        ],

        'user_message' => [
            'label' => 'Message',
            'plural' => 'Messages',

            'sections' => [
                'user_message_details' => 'User message details',
                'user_message_details_descr' => 'Manage direct messages between users.',
            ],

            'fields' => [
                'sender_id' => 'Sender',
                'receiver_id' => 'Receiver',
                'message' => 'Message content',
                'price' => 'Price (optional)',
                'replyTo' => 'Reply to message ID',
                'isSeen' => 'Is seen',
                 'story_id' => 'Story',
            ],

            'attachments' => [
                'title' => 'View :name attachments',
                'breadcrumb' => 'Attachments',
                'nav_label' => 'View attachments',
                'file_link' => 'Open file',
                'actions' => [
                    'create' => 'Add new attachment',
                ],
            ],

            'transactions' => [
                'title' => 'View :record payments',
                'breadcrumb' => 'Payments',
                'nav_label' => 'View payments',
                'fields' => [
                    'id' => 'ID',
                    'sender' => 'Sender',
                    'payer' => 'Payer',
                    'status' => 'Status',
                    'type' => 'Type',
                    'payment_provider' => 'Provider',
                    'amount' => 'Amount',
                ],
                'actions' => [
                    'create' => 'Add new transaction',
                ],
            ]

        ],

        'reaction' => [
            'label' => 'Reaction',
            'plural' => 'Reactions',

            'sections' => [
                'reaction_info' => 'Reaction info',
                'reaction_info_descr' => 'Details about the user and the type of reaction.',
                'target_content' => 'Target content',
                'target_content_descr' => 'Specify the content this reaction is attached to.',
            ],

            'fields' => [
                'user_id' => 'User',
                'reaction_type' => 'Reaction type',
                'post_id' => 'Post ID',
                'post_comment_id' => 'Comment ID'
            ],

            'types' => [
                'like' => 'Like',
            ],
        ],

        'user_list' => [
            'label' => 'List',
            'plural' => 'Lists',

            'sections' => [
                'list_details' => 'List details',
                'list_details_descr' => 'Provide a name and type for this user list.',
                'owner' => 'Owner',
                'owner_descr' => 'Select the user who owns this list.',
            ],

            'fields' => [
                'name' => 'List name',
                'type' => 'List type',
                'user_id' => 'List owner'
            ],

            'placeholders' => [
                'name' => 'Enter list name',
            ],

            'types' => [
                'blocked' => 'Blocked users',
                'following' => 'Following',
                'followers' => 'Followers',
                'custom' => 'Custom list',
            ],

            'members' => [
                'title' => 'View :name members',
                'breadcrumb' => 'Members',
                'navigation_label' => 'View members',
                'fields' => [
                    'id' => 'ID',
                    'username' => 'User',
                    'created_at' => 'Created at',
                ],
            ],
        ],

        'user_list_member' => [
            'label' => 'List Member',
            'plural' => 'List Members',

            'actions' => [
                'create' => 'Add new member',
            ],

            'sections' => [
                'list_association' => 'List Association',
                'list_association_descr' => 'Assign a user to a specific list.',
            ],

            'fields' => [
                'list_id' => 'User List ID',
                'user_id' => 'User',
            ],

            'placeholders' => [
                'list_id' => 'Select a list',
                'user_id' => 'Select a user',
            ],
        ],

        'user_bookmark' => [
            'label' => 'Bookmark',
            'plural' => 'Bookmarks',

            'sections' => [
                'bookmark_details' => 'Bookmark details',
                'bookmark_details_descr' => 'Link a user to a bookmarked post.',
            ],

            'fields' => [
                'user_id' => 'User',
                'post_id' => 'Post ID',
                'reel_id' => 'Reel ID',
                'username' => 'User'
            ],
        ],

        'user_report' => [
            'label' => 'User report',
            'plural' => 'User reports',

            'sections' => [
                'reporter_reported' => 'Reporter & reported users',
                'reporter_reported_descr' => 'Identify the user submitting the report and the user being reported.',

                'reported_content' => 'Reported content (optional)',
                'reported_content_descr' => 'Link this report to a specific piece of content.',

                'report_details' => 'Report details',
            ],

            'tabs' => [
                'all' => 'All',
                'received' => 'Received',
                'seen' => 'Seen',
                'solved' => 'Solved',
            ],

            'fields' => [
                'from_user_id' => 'Reporter',
                'user_id' => 'Reported user',
                'post_id' => 'Post ID',
                'message_id' => 'Message ID',
                'stream_id' => 'Stream ID',
                'type' => 'Report reason',
                'status' => 'Status',
                'details' => 'Additional details',
                'story_id' => 'Story ID',
                'reel_id' => 'Reel ID',
                'reel_comment_id' => 'Reel comment ID',
            ],

            'types' => [
                'i_dont_like' => 'I don’t like this',
                'spam' => 'Spam',
                'dmca' => 'DMCA',
                'offensive_content' => 'Offensive content',
                'abuse' => 'Abuse',
            ],

            'statuses' => [
                'received' => 'Received',
                'seen' => 'Seen',
                'solved' => 'Solved',
            ],

            'actions' => [
                'view_admin' => 'Open reported content',
                'view_public' => 'View public page',
            ],

            'navigation_badge_tooltip' => 'The number of open user reports',
        ],

        'featured_user' => [
            'label' => 'Featured user',
            'plural' => 'Featured users',

            'sections' => [
                'main' => 'Feature a user',
                'main_descr' => 'Select a user to highlight as featured on the platform.',
            ],

            'fields' => [
                'user_id' => 'Featured user',
                'username' => 'Username',
                'created_at' => 'Created at',
                'updated_at' => 'Updated at',
            ],
        ],

        'user_tax' => [
            'label' => 'Tax information',
            'plural' => 'Tax information',

            'sections' => [
                'user' => 'User association',
                'user_descr' => 'Link the tax information to a user and their issuing country.',

                'tax' => 'Tax identification',
                'tax_descr' => 'Legal and tax identification details.',

                'personal' => 'Personal details',
                'personal_descr' => 'Additional personal and address information.',
            ],

            'fields' => [
                'user_id' => 'User',
                'issuing_country_id' => 'Issuing country',
                'legal_name' => 'Legal name',
                'tax_identification_number' => 'Tax ID number',
                'vat_number' => 'VAT number',
                'tax_type' => 'Tax type',
                'date_of_birth' => 'Date of birth',
                'primary_address' => 'Primary address',
                'earnings_ytd' => 'Earnings YTD (gross)',
            ],

            'filters' => [
                'min_earnings' => 'Min earnings',
            ],

            'descriptions' => [
                'primary_address' => 'Enter full address',
            ],

            'placeholders' => [
                'user_id' => 'Select user',
                'issuing_country_id' => 'Select country',
            ],

            'options' => [
                'types' => [
                    'dac7' => 'DAC7',
                ],
            ],
        ],

        'post_comment' => [
            'label' => 'Comment',
            'plural' => 'Comments',

            'sections' => [
                'post_comment_details' => 'Post comment details',
                'post_comment_details_descr' => 'Post comment details.',
            ],

            'fields' => [
                'id' => 'ID',
                'author' => 'User',
                'message' => 'Message',
                'post_id' => 'Post'
            ],
        ],

        'attachment' => [
            'label' => 'Attachment',
            'plural' => 'Attachments',

            'sections' => [
                'file_and_metadata' => 'File & metadata',
                'associations' => 'Associations',
                'attachment_details' => 'Attachment details',
                'attachment_details_descr' => 'Configure or review the attachment details.',
            ],

            'fields' => [
                'id' => 'ID',
                'filename' => 'Filename',
                'file' => 'File',
                'driver' => 'Storage driver',
                'type' => 'Type',
                'user_id' => 'User',
                'post_id' => 'Post ID',
                'message_id' => 'Message ID',
                'payment_request_id' => 'Deposit request ID',
                'has_thumbnail' => 'Has thumbnail',
                'has_blurred_preview' => 'Has blurred preview',
                'open' => 'Open file',
                'story_id' => 'Story',
                'reel_id' => 'Reel',
                'sound_id' => 'Sound',
                'length'   => 'Duration',
            ],

            'help' => [
                'id' => 'UUID format preferred.',
                'driver' => 'Select which storage driver to use for the user assets.',
                'length' => 'Duration of the media file in seconds.',
            ],
        ],

        'poll' => [
            'label' => 'Poll',
            'plural' => 'Polls',

            'sections' => [
                'post_details' => 'Poll details',
                'post_details_descr' => 'Set up the poll details.',
            ],

            'fields' => [
                'user_id' => 'User',
                'post_id' => 'Post ID',
                'ends_at' => 'Ends at',
                'answer_id' => 'Selected answer',
                'answer' => 'Choice',
                'id' => 'Id',
            ],

            'filters' => [
                'poll.id' => 'Poll ID',
                'user.username' => 'Username',
            ],

            'poll_answers' => [
                'poll_choices' => 'Poll choices',
                'choices' => 'Choices',
                'actions' => [
                    'create' => 'Add new choice',
                    'edit' => 'Edit choice',
                    'delete' => 'Delete choice',
                ]
            ],

            'user_poll_answers' => [
                'label' => 'User answers',
                'fields' => [
                    'user_id' => 'User',
                    'answer_id' => 'Selected answer',
                    'answer' => 'Answer',
                ],
                'actions' => [
                    'create' => 'Add answer',
                    'edit' => 'Edit answer',
                    'delete' => 'Delete answer',
                ],
            ],
        ],

        'conversion_delivery' => [
            'label' => 'Conversion delivery',
            'plural' => 'Conversion deliveries',
            'navigation' => 'Conversion tracking',
            'view_all' => 'View all deliveries',
            'receipt_notice' => 'Check your provider’s dashboard to confirm receipt and attribution. Browser dispatch does not guarantee delivery.',
            'empty' => 'No conversion deliveries have been recorded yet.',
            'renewal' => 'Subscription renewal',
            'sections' => ['purchase' => 'Purchase', 'delivery' => 'Delivery', 'attribution' => 'Marketing consent'],
            'fields' => [
                'transaction' => 'Transaction', 'creator' => 'Creator', 'buyer' => 'Buyer',
                'purchase_type' => 'Purchase type', 'value' => 'Amount', 'method' => 'Delivery method',
                'status' => 'Status', 'attempts' => 'Attempts', 'sent_at' => 'Dispatched at',
                'available_at' => 'Next attempt', 'test_mode' => 'Dry run', 'event_id' => 'Event ID',
                'last_error' => 'Delivery message', 'counting_mode' => 'Counting rule',
                'consent_state' => 'Consent status', 'consented_at' => 'Consent granted',
                'expires_at' => 'Attribution expires', 'revoked_at' => 'Consent withdrawn',
            ],
            'statuses' => [
                'pending' => 'Pending', 'delivering' => 'Delivering', 'sent' => 'Sent',
                'browser_claimed' => 'Browser claimed', 'browser_dispatched' => 'Browser dispatched',
                'failed' => 'Failed', 'skipped' => 'Skipped', 'test' => 'Dry run',
            ],
            'purchase_types' => [
                'subscription' => 'Subscription', 'post_unlock' => 'Paid post',
                'message_unlock' => 'Paid message', 'stream_access' => 'Livestream access', 'tip' => 'Tip',
            ],
            'methods' => ['browser' => 'Browser pixel / tag', 'postback' => 'Server postback'],
            'counting_modes' => [
                'first_site' => 'First qualifying purchase on the site',
                'first_creator' => 'First qualifying purchase from each creator',
                'every_purchase' => 'Every qualifying purchase',
            ],
            'consent_states' => [
                'granted' => 'Granted', 'withdrawn' => 'Withdrawn', 'expired' => 'Expired', 'unavailable' => 'Unavailable',
            ],
        ],

        'transaction' => [

            'label' => 'Transaction',
            'plural' => 'Transactions',

            'sections' => [
                'participants' => 'Participants',
                'participants_descr' => 'Define the sender and recipient involved in the transaction.',

                'details' => 'Transaction details',
                'details_descr' => 'Set the status, type, provider, and core data.',

                'related' => 'Related entities',
                'related_descr' => 'Associate this transaction with content or subscriptions.',

                'provider_info' => 'Provider-specific info',
                'provider_info_descr' => 'Add optional IDs or tokens from external providers.',
            ],

            'fields' => [
                'sender_user_id' => 'Buyer',
                'recipient_user_id' => 'Seller',

                'status' => 'Status',
                'type' => 'Type',
                'payment_provider' => 'Payment provider',
                'currency' => 'Currency code',
                'amount' => 'Amount',
                'taxes' => 'Taxes',

                'subscription_id' => 'Subscription',
                'post_id' => 'Post',
                'stream_id' => 'Stream',
                'invoice_id' => 'Invoice',
                'user_message_id' => 'Message',

                'paypal_payer_id' => 'PayPal payer ID',
                'paypal_transaction_id' => 'PayPal transaction ID',
                'paypal_transaction_token' => 'PayPal transaction token',

                'stripe_transaction_id' => 'Stripe transaction ID',
                'stripe_session_id' => 'Stripe session ID',

                'coinbase_charge_id' => 'Coinbase charge ID',
                'coinbase_transaction_token' => 'Coinbase transaction token',

                'nowpayments_payment_id' => 'NowPayments payment ID',
                'nowpayments_order_id' => 'NowPayments order ID',

                'ccbill_transaction_token' => 'CCBill transaction token',
                'ccbill_transaction_id' => 'CCBill transaction ID',
                'ccbill_subscription_id' => 'CCBill subscription ID',

                'verotel_payment_token' => 'Verotel transaction token',
                'verotel_sale_id' => 'Verotel sale ID',

                'paystack_payment_token' => 'Paystack payment token',

                'mercado_payment_token' => 'Mercado Pago payment token',
                'mercado_payment_id' => 'Mercado Pago payment ID',
                'yookassa_payment_id' => 'YooMoney payment ID',
                'yookassa_payment_token' => 'YooMoney payment token',
                'mollie_payment_id' => 'Mollie payment ID',
                'mollie_payment_token' => 'Mollie payment token',
                'flutterwave_payment_id' => 'Flutterwave payment ID',
                'flutterwave_payment_token' => 'Flutterwave payment token',
                'coingate_order_id' => 'CoinGate order ID',
                'coingate_payment_token' => 'CoinGate callback token',
                'xendit_payment_id' => 'Xendit payment session ID',
                'xendit_payment_token' => 'Xendit payment token',
                'paddle_transaction_id' => 'Paddle transaction ID',
                'paddle_transaction_token' => 'Paddle transaction token',
                'cryptocom_payment_id' => 'Crypto.com payment ID',
                'cryptocom_payment_token' => 'Crypto.com payment token',

                'sender' => 'Sender',
                'receiver' => 'Recipient',
                'receiver_user_id' => 'Seller',
                'id' => 'ID'
            ],

            'helpers' => [
                'taxes' => 'JSON required. Examples can be taken out of app-created transactions.',
                'taxes_placeholder' => 'Enter tax breakdown or notes',
            ],

            'status_labels' => [
                'pending' => 'Pending',
                'refunded' => 'Refunded',
                'partially_paid' => 'Partially paid',
                'declined' => 'Declined',
                'initiated' => 'Initiated',
                'canceled' => 'Canceled',
                'approved' => 'Approved',
            ],

            'type_labels' => [
                'tip' => 'Tip',
                'deposit' => 'Deposit',
                'withdrawal' => 'Withdrawal',
                'chat_tip' => 'Chat tip',
                'stream_access' => 'Stream access',
                'message_unlock' => 'Message unlock',
                'post_unlock' => 'Post unlock',
                'one_month_subscription' => '1-month subscription',
                'three_months_subscription' => '3-month subscription',
                'six_months_subscription' => '6-month subscription',
                'yearly_subscription' => 'Yearly subscription',
                'subscription_renewal' => 'Subscription renewal',
            ],

            'tabs' => [
                'all' => 'All',
                'pending' => 'Pending',
                'approved' => 'Approved',
                'declined' => 'Declined',
            ],

        ],

        'post' => [
            'label' => 'Post',
            'plural' => 'Posts',

            'sections' => [
                'details' => 'Post details',
                'details_descr' => 'Set up the post details.',
                'settings' => 'Post settings',
                'settings_descr' => 'Pricing, status, and timing settings.',
            ],

            'fields' => [
                'user_id' => 'User',
                'text' => 'Post text',
                'price' => 'Price',
                'status' => 'Status',
                'release_date' => 'Release date',
                'expire_date' => 'Expire date',
                'is_pinned' => 'Pin this post',
            ],

            'actions' => [
                'post_url' => 'Post URL',
            ],

            'status_labels' => [
                '0' => 'Pending',
                '1' => 'Approved',
                '2' => 'Rejected',
            ],
        ],

        'hashtag' => [
            'label' => 'Hashtag',
            'plural' => 'Hashtags',

            'sections' => [
                'hashtag_info' => 'Hashtag information',
                'hashtag_info_descr' => 'Create and manage hashtags used across posts and comments.',
            ],

            'fields' => [
                'tag' => 'Tag',
                'tag_helper' => 'Enter the hashtag without the “#”. Only letters, numbers, and underscores are allowed (max 64). Stored in lowercase.',
            ],
        ],

        'hashtag_link' => [
            'label' => 'Hashtag link',
            'plural' => 'Hashtag links',
            'fields' => [
                'post_id' => 'Post ID',
                'post_comment_id' => 'Comment ID',
            ],
        ],

        'subscription' => [
            'label' => 'Subscription',
            'plural' => 'Subscriptions',

            'sections' => [
                'user_info' => 'User info',
                'subscription_details' => 'Subscription details',
                'platform_identifiers' => 'Platform identifiers',
                'timestamps' => 'Timestamps',
            ],

            'fields' => [
                'sender_user_id' => 'Subscriber',
                'recipient_user_id' => 'Creator',

                'subscriber.username' => 'Subscriber',
                'creator.username' => 'Creator',

                'type' => 'Type',
                'status' => 'Status',
                'provider' => 'Payment provider',
                'amount' => 'Amount',

                'paypal_agreement_id' => 'PayPal agreement ID',
                'paypal_plan_id' => 'PayPal plan ID',
                'stripe_subscription_id' => 'Stripe subscription ID',
                'ccbill_subscription_id' => 'CCBill subscription ID',
                'verotel_sale_id' => 'Verotel sale ID',

                'expires_at' => 'Expires at',
                'canceled_at' => 'Canceled at',
            ],

            'status_labels' => [
                'active' => 'Active',
                'completed' => 'Completed',
                'canceled' => 'Canceled',
                'suspended' => 'Suspended',
                'expired' => 'Expired',
                'failed' => 'Failed',
                'pending' => 'Pending',
            ],

            'tabs' => [
                'all' => 'All',
                'pending' => 'Pending',
                'active' => 'Active',
                'canceled' => 'Canceled',
            ],

            'type_labels' => [
                'one_month_subscription' => '1-month subscription',
                'three_months_subscription' => '3-month subscription',
                'six_months_subscription' => '6-month subscription',
                'yearly_subscription' => '1-year subscription',
            ],
        ],

        'withdrawal' => [
            'label' => 'Withdrawal',
            'plural' => 'Withdrawals',

            'sections' => [
                'details' => 'Withdrawal details',
                'details_descr' => 'Configure or review the withdrawal request details.',
                'payout_summary' => 'Payout summary',
                'payout_details' => 'Payout details',
                'summary' => 'Withdrawal summary',
                'documents' => 'Uploaded documents',
                'processing' => 'Processing',
                'processing_descr' => 'Review and update the withdrawal status and request fields.',
            ],

            'fields' => [
                'id' => 'ID',
                'username' => 'User',
                'amount' => 'Amount',
                'requested_amount' => 'Requested amount',
                'fee' => 'Fee',
                'net_payout' => 'Net payout',
                'status' => 'Status',
                'processed' => 'Processed',
                'payment_method' => 'Payment method',
                'payout_method_key' => 'Payout method key',
                'payout_account' => 'Saved payout account',
                'payment_identifier' => 'Payment identifier',
                'stripe_payout_id' => 'Stripe payout ID',
                'stripe_transfer_id' => 'Stripe transfer ID',
                'document' => 'Document',
                'document_field' => 'Purpose',
                'file_size' => 'Size',
                'user_id' => 'User',
                'message' => 'Message',
                'notes' => 'Notes',
                'details_label' => 'Details',
                'iban' => 'IBAN',
                'paypal_email' => 'PayPal email',
                'wallet_address' => 'Wallet address',
                'payout_destination' => 'Payout destination',
                'stripe_account' => 'Stripe account',
                'account_label' => 'Account label',
                'account_holder' => 'Account holder',
                'swift_bic' => 'SWIFT/BIC',
                'bank' => 'Bank',
                'bank_address' => 'Bank address',
                'country' => 'Country',
                'method' => 'Method',
            ],

            'helpers' => [
                'stripe_connect_warning' => 'Withdrawals using Stripe Connect can only be created by creators',
                'status_creation_rule' => 'A new withdrawal must be created with the requested status.',
                'processed_warning' => 'This withdrawal request has already been processed',
                'reserved_details_locked' => 'The amount, owner and fee cannot change after the withdrawal balance has been reserved.',
                'amount_overflow' => "This user's credit balance is lower than the withdrawal amount. Try a lower amount",
                'fees_info' => "Fees are auto-calculated, if withdrawal fees are enabled in payment settings.",
                'summary_empty' => 'The payout summary will appear after the withdrawal is created.',
                'payout_details_empty' => 'No saved payout details on this withdrawal.',
                'stored_notes' => 'Stored user notes submitted with this withdrawal.',
                'stored_method_reference' => 'Stored withdrawal method for reference only.',
                'no_payout_accounts' => 'Add a saved payout account for this user and method before creating a withdrawal.',
                'stored_payout_reference' => 'Stored payout destination for reference only.',
                'stored_payout_used' => 'Stored payout destination used for this withdrawal.',
                'stripe_payout_reference' => 'Stripe-generated payout reference.',
                'stripe_transfer_reference' => 'Stripe-generated transfer reference.',
                'processed_flag' => 'Set automatically when the withdrawal is approved or rejected. It cannot be changed manually.',
            ],

            'status_labels' => [
                'approved' => 'Approved',
                'requested' => 'Requested',
                'rejected' => 'Rejected',
            ],

            'actions' => [
                'approve' => 'Approve',
                'reject' => 'Reject',
            ],

            'tabs' => [
                'all' => 'All',
                'requested' => 'Requested',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
            ],

            'export' => [
                'csv' => 'Payouts CSV',
                'gross' => 'Gross',
                'net' => 'Net',
                'method' => 'Method',
                'identifier' => 'Identifier',
                'saved_account' => 'Saved account',
                'payout_details' => 'Payout details',
                'yes' => 'Yes',
                'no' => 'No',
            ],

            'navigation_badge_tooltip' => 'The number of pending withdrawals',
        ],

        'payment_request' => [
            'label' => 'Deposit request',
            'plural' => 'Deposit requests',
            'navigation_badge_tooltip' => 'The number of pending deposit requests',

            'sections' => [
                'payment_request' => 'Deposit request',
            ],

            'fields' => [
                'user_id' => 'User',
                'transaction_id' => 'Transaction ID',
                'amount' => 'Amount',
                'status' => 'Status',
                'type' => 'Type',
                'reason' => 'Rejection reason',
                'message' => 'Message',
            ],

            'status_labels' => [
                'approved' => 'Approved',
                'pending' => 'Pending',
                'rejected' => 'Rejected',
            ],

            'type_labels' => [
                'deposit' => 'Deposit',
            ],

            'actions' => [
                'approve' => 'Approve',
                'reject' => 'Reject',
            ],

            'tabs' => [
                'all' => 'All',
                'pending' => 'Pending',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
            ],
        ],

        'invoice' => [
            'label' => 'Invoice',
            'plural' => 'Invoices',

            'sections' => [
                'invoice_info' => 'Invoice information',
                'invoice_info_descr' => 'Here you can see the encoded data of a generated invoice.',
            ],

            'fields' => [
                'invoice_id' => 'Invoice ID',
                'transaction_id' => 'Transaction ID',
                'data' => 'Data',
            ],

            'actions' => [
                'invoice_url' => 'Invoice URL',
            ],
        ],

        'tax' => [
            'label' => 'Tax',
            'plural' => 'Taxes',

            'sections' => [
                'details' => 'Tax details',
                'details_descr' => 'Edit your site fees details.',
            ],

            'fields' => [
                'name' => 'Name',
                'type' => 'Type',
                'percentage' => 'Value',
                'country_name' => 'Country',
                'countries_name' => 'Countries',
                'hidden' => 'Hidden',
            ],

            'type_labels' => [
                'fixed' => 'Fixed',
                'exclusive' => 'Exclusive',
                'inclusive' => 'Inclusive',
            ],
        ],

        'country' => [
            'label' => 'Country',
            'plural' => 'Countries',

            'sections' => [
                'country_details' => 'Country Details',
                'country_details_descr' => 'Country/region details.',
            ],

            'fields' => [
                'name' => 'Name',
                'country_code' => 'Country Code',
                'phone_code' => 'Phone Code',
                'created_at' => 'Created At',
                'updated_at' => 'Updated At',
            ],
        ],

        'stream' => [
            'label' => 'Stream',
            'plural' => 'Streams',

            'sections' => [
                'stream_details' => 'Stream details',
                'stream_details_descr' => 'Basic details about the stream.',
                'stream_source' => 'Stream source & playback',
                'stream_source_descr' => 'Configuration for stream delivery & RTMP.',
                'advanced_metadata' => 'Advanced & metadata',
            ],

            'fields' => [
                'name' => 'Stream name',
                'slug' => 'Slug',
                'price' => 'Access price',
                'user_id' => 'User',
                'poster' => 'Poster image',
                'status' => 'Status',
                'requires_subscription' => 'Requires subscription',
                'is_public' => 'Public stream',
                'sent_expiring_reminder' => 'Sent expiration reminder',

                'driver' => 'Streaming driver',
                'pushr_id' => 'Pushr ID',
                'rtmp_key' => 'RTMP key',
                'rtmp_server' => 'RTMP server',
                'hls_link' => 'HLS playback link',
                'vod_link' => 'VOD link',

                'settings' => 'Stream settings (JSON)',
                'ended_at' => 'Ended at',
                'created_at' => 'Created',
                'updated_at' => 'Updated',
            ],

            'status_labels' => [
                'all' => 'All',
                'in_progress' => 'In progress',
                'ended' => 'Ended',
                'deleted' => 'Deleted',
            ],

            'driver_labels' => [
                1 => 'PushrCDN',
                2 => 'LiveKit',
            ],
        ],

        'stream_message' => [
            'label' => 'Stream message',
            'plural' => 'Stream messages',

            'sections' => [
                'message_details' => 'Message details',
            ],

            'fields' => [
                'user_id' => 'User',
                'stream_id' => 'Stream',
                'message' => 'Message content',
                'created_at' => 'Created at',
                'updated_at' => 'Updated at',
            ],

            'help' => [
                'user_id' => 'Select the user who sent the message.',
                'stream_id' => 'Choose the stream to associate this message with.',
                'message' => 'The content of the chat message.',
            ],
        ],

        'public_page' => [
            'label' => 'Public page',
            'plural' => 'Public pages',

            'sections' => [
                'page_details' => 'Page details',
                'page_details_descr' => 'Configure the content and structure of this public page.',
                'display_settings' => 'Display settings',
                'display_settings_descr' => 'Control how and where this page appears.',
            ],

            'fields' => [
                'title' => 'Title',
                'title_helper' => 'Page title shown in header and list.',
                'short_title' => 'Short title',
                'short_title_helper' => 'Alternative shorter title used for navigation or menus.',
                'slug' => 'Slug',
                'content' => 'Content',
                'slug_helper' => 'Unique identifier used in the URL (no spaces or special characters).',
                'shown_in_footer' => 'Shown in footer',
                'shown_in_footer_helper' => 'Enable to show this page in the site footer.',
                'is_tos' => 'Terms of service',
                'is_tos_helper' => 'Enable if this page represents the Terms of Service.',
                'is_privacy' => 'Privacy policy',
                'is_privacy_helper' => 'Enable if this page represents the Privacy Policy.',
                'show_last_update_date' => 'Show last update date',
                'show_last_update_date_helper' => 'If enabled, shows the last modification date on the page.',
                'page_order' => 'Page order',
                'page_order_helper' => 'Defines the order in which this page appears in listings.',
                'page_url' => 'Page URL',
                'destination' => 'Destination',
                'internal_page' => 'Internal page',
                'external_link' => 'External link',
                'external_url' => 'External URL',
                'external_url_helper' => 'Enter a full http:// or https:// URL. The local page URL will redirect here.',
                'open_in_new_tab' => 'Open in a new tab',
            ],
        ],

        'contact_message' => [
            'label' => 'Contact message',
            'plural' => 'Contact messages',

            'fields' => [
                'email' => 'Email',
                'subject' => 'Subject',
                'message' => 'Message',
                'status' => 'Status',
                'is_replied' => 'Replied',
                'replied_at' => 'Replied at',
                'replied_by' => 'Replied by',
                'reply_details' => 'Reply details',
                'created_at' => 'Created at',
                'updated_at' => 'Updated at',
            ],

            'status' => [
                'pending' => 'Pending',
                'replied' => 'Replied',
                'unknown_replier' => 'unknown admin',
            ],

            'helpers' => [
                'is_replied' => 'Use this after replying from your email client.',
            ],

            'reply_details' => 'Marked replied on :date by :user.',

            'filters' => [
                'reply_status' => 'Reply status',
            ],

            'actions' => [
                'reply_via_email' => 'Reply via email',
                'mark_replied' => 'Mark as replied',
                'mark_unreplied' => 'Mark as pending',
            ],
        ],

        'global_announcement' => [
            'label' => 'Announcement',
            'plural' => 'Announcements',

            'fields' => [
                'content' => 'Content',
                'size' => 'Size',
                'expiring_at' => 'Expiring at',
                'is_published' => 'Published',
                'is_dismissible' => 'Dismissible',
                'is_sticky' => 'Sticky',
                'is_global' => 'Global',
                'id_verified_only' => 'ID-verified only',
            ],

            'helpers' => [
                'is_published' => 'Whether the announcement is visible to users.',
                'is_dismissible' => 'Allows users to close or hide this announcement.',
                'is_sticky' => 'Keeps the announcement pinned at the top.',
                'is_global' => 'Shows the announcement to all users across the system.',
                'id_verified_only' => 'Visible only to users who have verified their ID.',
            ],

            'sections' => [
                'content' => 'Content',
                'content_descr' => 'Announcement details.',
                'visibility' => 'Visibility',
                'visibility_descr' => 'Enable/disable display behaviors.',
            ],

            'size_labels' => [
                'regular' => 'Regular',
                'small' => 'Small',
            ],
        ],

        'reward' => [
            'label'  => 'Referral',
            'plural' => 'Referrals',

            'sections' => [
                'referral_info'       => 'Referral reward info',
                'referral_info_descr' => 'Assign rewards generated from referral activity.',
            ],

            'fields' => [
                'id'                     => 'ID',
                'from_user_id'           => 'Referrer',
                'to_user_id'             => 'Referred user',
                'referral_code_usage_id' => 'Referral code usage',
                'amount'                 => 'Reward amount',
                'transaction_id'         => 'Transaction ID',
                'reward_type'            => 'Reward type',
            ],

            'help' => [
                'reward_type' => 'Type code for the reward.',
            ],
        ],

        'story' => [
            'label'  => 'Story',
            'plural' => 'Stories',

            'sections' => [
                'details'       => 'Story details',
                'details_descr' => 'Core story fields and ownership.',
                'settings'      => 'Story settings',
                'settings_descr'=> 'Visibility, expiry, links and display options.',
                'overlay'       => 'Overlay',
                'overlay_descr' => 'Overlay payload (JSON) used by the viewer (e.g. x/y).',
            ],

            'fields' => [
                'user_id'     => 'User',
                'mode'        => 'Mode',
                'text'        => 'Text',
                'overlay'     => 'Overlay',
                'bg_preset'   => 'Background preset',
                'is_public'   => 'Public',
                'is_highlight'=> 'Highlighted',
                'expires_at'  => 'Expires at',
                'sound_id'    => 'Sound',
                'views'       => 'Views',
                'link_url'    => 'Link URL',
                'link_text'   => 'Link label',
            ],

            'mode_labels' => [
                'media' => 'Photo / Video',
                'text'  => 'Text',
            ],

            'help' => [
                'overlay'     => 'Stored as JSON (x/y).',
                'sound_id'    => 'Optional: sound attached to this story.',
                'bg_preset'   => 'Only applies to text stories.',
                'link_url'    => 'Must start with http:// or https://',
                'link_text'   => 'Shown as the CTA label in the viewer.',
            ],

            'actions' => [
                'view_in_app' => 'View in app',
            ],
        ],

        'reel' => [
            'label'  => 'Reel',
            'plural' => 'Reels',

            'sections' => [
                'details'       => 'Reel details',
                'details_descr' => 'Core reel fields and ownership.',
                'settings'      => 'Reel settings',
                'settings_descr'=> 'Visibility and display options.',
                'overlay'       => 'Overlay',
                'overlay_descr' => 'Overlay payload (JSON) used by the viewer.',
            ],

            'fields' => [
                'user_id'   => 'User',
                'caption'   => 'Caption',
                'overlay'   => 'Overlay',
                'is_public' => 'Public',
                'sound_id'  => 'Sound',
                'views'     => 'Views',
                'comments'  => 'Comments',
                'reactions' => 'Reactions',
                'bookmarks' => 'Bookmarks',
            ],

            'help' => [
                'overlay'  => 'Stored as JSON.',
                'sound_id' => 'Optional: sound attached to this reel.',
            ],

            'actions' => [
                'view_in_app' => 'View in app',
            ],
        ],

        'reel_comment' => [
            'label'  => 'Reel comment',
            'plural' => 'Reel comments',

            'fields' => [
                'id'        => 'ID',
                'user_id'   => 'User',
                'parent_id' => 'Parent comment',
                'message'   => 'Message',
                'reactions' => 'Reactions',
            ],
        ],

        'sound' => [
            'label'  => 'Sound',
            'plural' => 'Sounds',

            'sections' => [
                'details'        => 'Sound details',
                'details_descr'  => 'Basic information about the sound.',
                'settings'       => 'Settings',
                'settings_descr' => 'Sound visibility and availability controls.',
                'media'          => 'Media',
                'media_descr'    => 'Audio file and cover image associated with this sound.',
            ],

            'fields' => [
                'title'       => 'Title',
                'artist'      => 'Artist',
                'description' => 'Description',
                'is_active'   => 'Active',
                'cover'       => 'Cover',
                'audio'       => 'Audio file',
                'length'      => 'Duration',
                'attachments' => 'Attachments'
            ],

            'help' => [
                'title'       => 'Displayed name of the sound.',
                'artist'      => 'Artist or author of the sound.',
                'description' => 'Optional description for administrative use.',
                'is_active'   => 'Only active sounds can be selected in stories and reels.',
                'cover'       => 'Cover image shown in the sound selector.',
                'audio'       => 'Primary audio file associated with this sound.',
            ],

            'validation' => [
                'cover_required' => 'A cover image is required.',
                'audio_required' => 'An audio file is required.',
            ],

            'actions' => [
                'view_attachments' => 'View attachments',
            ],
        ],

    ],

    'settings_forms' => [
        'security' => [
            'email_domains' => [
                'tab' => 'Email domains',
                'fields' => [
                    'domain_policy' => 'Domain policy',
                    'allowedlist_domains' => 'Allowedlist domains',
                    'blocklist_domains' => 'Blocklist domains',
                ],
                'options' => [
                    'allow_all' => 'Allow all domains',
                    'allowlist_only' => 'Allow only allowlisted domains',
                    'blocklist_only' => 'Block only blocklisted domains',
                ],
                'helpers' => [
                    'domain_policy' => 'Controls which email domains can be used during registration.',
                    'allowedlist_domains' => 'Used when policy is “Allow only allowlisted domains”. Enter domains like: example.com (no scheme).',
                    'blocklist_domains' => 'Used when policy is “Block only blocklisted domains”. Enter domains like: bad.com (no scheme).',
                ],
                'placeholders' => [
                    'domains' => 'Add a domain and press Enter',
                ],
            ],
            'rate_limits' => [
                'tab' => 'Rate limits',
                'fields' => [
                    'enable_feature_rate_limits' => 'Enable endpoint rate limits',
                    'enabled' => 'Enabled',
                    'max_attempts' => 'Max attempts',
                    'window' => 'Window',
                ],
                'helpers' => [
                    'enable_feature_rate_limits' => 'Adds admin-configurable anti-abuse throttles to selected write and generate actions.',
                    'enabled' => 'Turn this feature-specific limit on or off.',
                    'max_attempts' => 'How many requests are allowed within the time window.',
                    'window' => 'How long to keep counting attempts before the limit resets.',
                ],
                'units' => [
                    'seconds' => 'seconds',
                ],
                'features' => [
                    'posts_save' => [
                        'title' => 'Posts save',
                        'description' => 'Applies when users create or update posts.',
                    ],
                    'posts_comments_add' => [
                        'title' => 'Posts comments add',
                        'description' => 'Applies when users add comments to posts.',
                    ],
                    'stories_store' => [
                        'title' => 'Stories store',
                        'description' => 'Applies when users publish a story.',
                    ],
                    'reels_store' => [
                        'title' => 'Reels store',
                        'description' => 'Applies when users publish a reel.',
                    ],
                    'reels_comments_add' => [
                        'title' => 'Reels comments add',
                        'description' => 'Applies when users add comments to reels.',
                    ],
                    'streams_init' => [
                        'title' => 'Streams init',
                        'description' => 'Applies when creators start a new stream.',
                    ],
                    'stream_comments_add' => [
                        'title' => 'Stream comments add',
                        'description' => 'Applies when viewers send messages in stream chat.',
                    ],
                    'suggestions_generate' => [
                        'title' => 'AI suggestions generate',
                        'description' => 'Applies when users generate AI-powered suggestions.',
                    ],
                    'profile_asset_generate' => [
                        'title' => 'Profile AI assets',
                        'description' => 'Applies when users generate avatar or cover images with AI.',
                    ],
                    'messenger_send' => [
                        'title' => 'Messenger send',
                        'description' => 'Applies when users send direct messages.',
                    ],
                ],
            ],
        ],
        'payments' => [
            'withdrawals' => [
                'fields' => [
                    'manual_payout_methods' => 'Manual payout methods',
                    'custom_withdrawal_message' => 'Custom withdrawal message',
                ],
                'helpers' => [
                    'manual_payout_methods' => 'Choose which manual payout methods users can request. Labels come from your translation files.',
                    'custom_withdrawal_message' => 'Shows additional info near the withdrawal form for manual payout methods.',
                ],
            ],
        ],
    ],

    'settings' => [
        'general' => 'General',
        'users' => 'Users',
        'feed' => 'Feed',
        'media' => 'Media',
        'media_moderation' => 'Media moderation',
        'storage' => 'Storage',
        'runtime' => 'Runtime',
        'payments' => 'Payments',
        'websockets' => 'Websockets',
        'emails' => 'Emails',
        'streams' => 'Streams',
        'stories' => 'Stories',
        'reels' => 'Reels',
        'compliance' => 'Compliance',
        'security' => 'Security',
        'referrals' => 'Referrals',
        'ai' => 'AI',
        'admin' => 'Admin',
        'theme' => 'Theme',
        'license' => 'License',
    ],

];
