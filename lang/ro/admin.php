<?php

return [

    'common' => [
        'created_at' => 'Creat la',
        'updated_at' => 'Actualizat la',
        'expiring_at' => 'Expiră la',
        'canceled_at' => 'Anulat la',
        'create' => 'Adaugă',
        'edit' => 'Actualizat',
        'delete' => 'Șterge',
        'view' => 'Vizualizare',
        'id' => 'ID',
        'files' => 'Fișiere',
    ],


    'dashboard' => [

    ],

    'navigation' => [
        'dashboard' => 'Panou de control',
        'groups' => [
            'users' => 'Utilizatori',
            'moderation' => 'Moderare',
            'content' => 'Conținut',
            'posts' => 'Postări',
            'finances' => 'Finanțe',
            'taxes' => 'Taxe',
            'stories' => 'Conținut scurt',
            'streams' => 'Transmisiuni',
            'site' => 'Site',
            'settings' => 'Setări',
        ],
    ],

    'filters' => [
        'title' => 'Filtre',
        'start_date' => 'Data de început',
        'end_date' => 'Data de sfârșit',
        'today' => 'Astăzi',
        'week' => 'Ultima săptămână',
        'month' => 'Ultima lună',
        'year' => 'Anul acesta',
        'last_month' => 'Ultimele 30 de zile',
        'last_year' => 'Ultimele 12 luni',
    ],

    'widgets' => [
        'stats_overview' => [
            'title' => 'Statistici din ultimele 7 zile',

            'revenue' => [
                'label' => 'Venituri',
                'description' => 'Venit',
            ],
            'new_users' => [
                'label' => 'Utilizatori',
                'description' => 'Utilizatori noi',
            ],
            'new_payments' => [
                'label' => 'Plăți',
                'description' => 'Plăți finalizate',
            ],
        ],

        'users_chart' => [
            'title' => 'Utilizatori',
            'datasets' => [
                'users' => 'Utilizatori',
                'user_messages' => 'Mesaje utilizatori',
            ],
        ],

        'posts_chart' => [
            'title' => 'Postări',
            'filters' => [
                'today' => 'Astăzi',
                'week' => 'Ultima săptămână',
                'month' => 'Ultima lună',
                'year' => 'Anul acesta',
            ],
            'datasets' => [
                'posts' => 'Postări',
                'comments' => 'Comentarii',
                'reactions' => 'Reacții',
            ],
        ],

        'transactions_chart' => [
            'title' => 'Plăți',
            'filters' => [
                'today' => 'Astăzi',
                'week' => 'Ultima săptămână',
                'month' => 'Ultima lună',
                'year' => 'Anul acesta',
            ],
            'datasets' => [
                'transactions' => 'Plăți',
                'subscriptions' => 'Abonamente',
            ],
        ],

        'product_info' => [
            'title' => 'Ghid rapid',
            'website' => [
                'title' => 'Website',
                'description' => 'Vizitează pagina oficială a produsului',
            ],
            'documentation' => [
                'title' => 'Documentație',
                'description' => 'Vizitează documentația a produsului',
            ],
            'changelog' => [
                'title' => 'Jurnal de modificări',
                'description' => 'Vizitează jurnalul de modificări',
            ],
        ],

        'transaction_stats' => [
            'heading' => 'Plăți din acest an',
            'total' => 'Total plăți',
            'completed' => 'Plăți finalizate',
            'average' => 'Preț mediu',
        ],

        'subscription_stats' => [
            'heading' => 'Abonamentele acestui an',
            'total' => 'Total abonamente',
            'active' => 'Abonamente active în prezent',
            'average_price' => 'Preț mediu',
        ],

    ],

    'resources' => [
        'user' => [
            'label' => 'Utilizator',
            'plural' => 'Utilizatori',

            'sections' => [
                'account_info' => 'Informații cont',
                'paywall_info' => 'Informații Paywall',
                'profile_info' => 'Informații profil',
                'withdrawals_info' => 'Informații retrageri',
                'security_info' => 'Informații securitate',
                'billing_info' => 'Informații facturare',
            ],

            'fields' => [
                'id' => 'ID',
                'name' => 'Nume',
                'email' => 'Email',
                'username' => 'Utilizator',
                'password' => 'Parolă',
                'roles' => 'Rol',
                'email_verified_at' => 'Email verificat la',
                'identity_verified_at' => 'ID verificat la',
                'birthdate' => 'Data nașterii',
                'paid_profile' => 'Profil plătit',
                'public_profile' => 'Profil public',
                'open_profile' => 'Profil deschis',
                'profile_access_price' => 'Preț acces',
                'profile_access_price_3_months' => 'Preț 3 luni',
                'profile_access_price_6_months' => 'Preț 6 luni',
                'profile_access_price_12_months' => 'Preț 12 luni',
                'current_avatar' => 'Avatar actual',
                'avatar' => 'Avatar',
                'current_cover' => 'Copertă actuala',
                'cover' => 'Copertă',
                'bio' => 'Biografie',
                'location' => 'Locație',
                'gender_id' => 'Gen',
                'gender_pronoun' => 'Pronume',
                'website' => 'Website',
                'referral_code' => 'Cod recomandare',
                'stripe_account_id' => 'ID Stripe Connect',
                'country_id' => 'Țară Stripe Connect',
                'stripe_onboarding_verified' => 'Stripe verificat',
                'last_ip' => 'Ultimul IP',
                'last_active_at' => 'Ultima activitate',
                'enable_geoblocking' => 'Activează geo-blocare',
                'enable_2fa' => 'Activează 2FA',
                'billing_address' => 'Adresă facturare',
                'first_name' => 'Prenume',
                'last_name' => 'Nume',
                'city' => 'Oraș',
                'country' => 'Țară',
                'state' => 'Județ',
                'postcode' => 'Cod poștal',
                'gender' => 'Gen',
            ],

            'actions' => [
                'impersonate' => 'Impersonare',
                'profile_url' => 'URL profil',
            ],
        ],

        'user_verify' => [
            'label' => 'Verificare ID',
            'plural' => 'Verificări ID',

            'sections' => [
                'verification_details' => 'Detalii verificare',
                'verification_details_descr' => 'Administrează cererea de verificare a utilizatorului.',
            ],

            'tabs' => [
                'all' => 'Toate',
                'pending' => 'În așteptare',
                'approved' => 'Aprobat',
                'rejected' => 'Respins',
            ],

            'fields' => [
                'user_id' => 'Utilizator',
                'status' => 'Stare',
                'rejectionReason' => 'Motiv respingere',
                'files' => 'Vizualizare fișiere'
            ],

            'actions' => [
                'profile_url' => 'URL profil',
                'approve' => 'Aprobă',
                'reject' => 'Respinge',
            ],

            'navigation_badge_tooltip' => 'Numărul de verificări în procesare',
        ],

        'release_form' => [
            'label' => 'Formular de acord',
            'plural' => 'Formulare de acord',

            'sections' => [
                'release_form_details' => 'Detalii formular de acord',
            ],

            'tabs' => [
                'all' => 'Toate',
                'pending' => 'În așteptare',
                'approved' => 'Aprobate',
                'rejected' => 'Respinse',
            ],

            'status_labels' => [
                'pending' => 'În așteptare',
                'approved' => 'Aprobat',
                'rejected' => 'Respins',
            ],

            'fields' => [
                'user_id' => 'Creator',
                'title' => 'Titlu',
                'status' => 'Stare',
                'files' => 'Fișiere',
                'reviewed_by' => 'Verificat de',
                'reviewed_at' => 'Verificat la',
                'notes' => 'Note creator',
                'rejection_reason' => 'Motiv respingere',
            ],

            'actions' => [
                'approve' => 'Aprobă',
                'reject' => 'Respinge',
            ],

            'navigation_badge_tooltip' => 'Numărul de formulare de acord în așteptare',
        ],

        'user_deletion_request' => [
            'label' => 'Cerere ștergere',
            'plural' => 'Cereri ștergere',

            'sections' => [
                'request_details' => 'Detalii cerere',
            ],

            'tabs' => [
                'all' => 'Toate',
                'pending' => 'În așteptare',
                'approved' => 'Aprobate',
                'blocked' => 'Blocate',
                'completed' => 'Finalizate',
            ],

            'status_labels' => [
                'pending' => 'În așteptare',
                'approved' => 'Aprobată',
                'rejected' => 'Respinsă',
                'canceled' => 'Anulată',
                'completed' => 'Finalizată',
                'blocked' => 'Blocată',
            ],

            'mode_labels' => [
                'manual_review' => 'Doar aprobare admin',
                'cooldown_auto' => 'Automat după perioada de așteptare',
                'manual_review_with_cooldown' => 'Aprobare admin plus perioadă de așteptare',
            ],

            'fields' => [
                'user_id' => 'Utilizator',
                'requested_name' => 'Nume la cerere',
                'requested_email' => 'Email la cerere',
                'requested_username' => 'Utilizator la cerere',
                'status' => 'Stare',
                'mode' => 'Flux',
                'reason' => 'Motiv utilizator',
                'admin_notes' => 'Note admin',
                'rejection_reason' => 'Motiv respingere',
                'requested_at' => 'Solicitat la',
                'eligible_for_deletion_at' => 'Eligibilă pentru ștergere la',
                'reviewed_by' => 'Verificată de',
                'reviewed_at' => 'Verificată la',
                'canceled_at' => 'Anulată la',
                'completed_at' => 'Finalizată la',
            ],

            'actions' => [
                'approve' => 'Aprobă ștergerea',
                'approve_modal_heading' => 'Aprobi cererea de ștergere?',
                'approve_modal_description' => 'Aceasta aprobă cererea. Ștergerea va rula doar după perioada de așteptare configurată și după verificările finale ale contului.',
                'approve_modal_description_manual_review' => 'Acest flux nu are perioadă de așteptare. Dacă verificările finale ale contului trec, aprobarea acestei cereri șterge contul imediat.',
                'approve_modal_submit' => 'Aprobă cererea',
                'reject' => 'Respinge',
                'cancel' => 'Anulează cererea',
            ],

            'navigation_badge_tooltip' => 'Numărul cererilor de ștergere cont în așteptare sau blocate',
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
            'label' => 'Notificare',
            'plural' => 'Notificări',

            'sections' => [
                'general_info' => 'Informații generale',
                'notification_details' => 'Detalii notificare',
                'linked_models' => 'Modele asociate',
            ],

            'fields' => [
                'id' => 'ID notificare',
                'from_user_id' => 'De la utilizator',
                'to_user_id' => 'Către utilizator',
                'type' => 'Tip notificare',
                'read' => 'Citit',
                'post_id' => 'ID postare',
                'post_comment_id' => 'ID comentariu',
                'subscription_id' => 'ID abonament',
                'transaction_id' => 'ID tranzacție',
                'reaction_id' => 'ID reacție',
                'withdrawal_id' => 'ID retragere',
                'user_message_id' => 'ID mesaj utilizator',
                'stream_id' => 'ID stream',
            ],

            'helper_texts' => [
                'id' => 'Format UUID recomandat.',
                'read' => 'Indică dacă utilizatorul a văzut notificarea.',
            ],

            'types' => [
                'ppv_unlock' => 'Conținut deblocat',
                'expiring_stream' => 'Stream expiră',
                'new_message' => 'Mesaj nou',
                'withdrawal_action' => 'Actualizare retragere',
                'new_subscription' => 'Abonament nou',
                'new_comment' => 'Comentariu nou',
                'comment_reply' => 'Răspuns la comentariu',
                'new_reaction' => 'Reacție nouă',
                'new_tip' => 'Tips nou',
                'mention' => 'Mentiune',
            ],
        ],

        'user_message' => [
            'label' => 'Mesaj',
            'plural' => 'Mesaje',

            'sections' => [
                'user_message_details' => 'Detalii mesaj utilizator',
                'user_message_details_descr' => 'Gestionează mesajele directe dintre utilizatori.',
            ],

            'fields' => [
                'sender_id' => 'Expeditor',
                'receiver_id' => 'Destinatar',
                'message' => 'Conținut mesaj',
                'price' => 'Preț (opțional)',
                'replyTo' => 'ID mesaj răspuns',
                'isSeen' => 'Este văzut',
                'story_id' => 'ID Story',
            ],

            'attachments' => [
                'title' => 'Vizualizare atașamente',
                'breadcrumb' => 'Atașamente',
                'nav_label' => 'Vezi atașamente',
                'file_link' => 'Deschide fișierul',
                'file' => 'Fișier',
                'actions' => [
                    'create' => 'Adaugă atașament',
                ],
            ],

            'transactions' => [
                'title' => 'Vizualizare plăți',
                'breadcrumb' => 'Plăți',
                'nav_label' => 'Vezi plăți',
                'fields' => [
                    'id' => 'ID',
                    'sender' => 'Expeditor',
                    'payer' => 'Plătitor',
                    'status' => 'Stare',
                    'type' => 'Tip',
                    'payment_provider' => 'Furnizor',
                    'amount' => 'Sumă',
                ],
                'actions' => [
                    'create' => 'Adăugare tranzacție',
                ],
            ],

        ],

        'reaction' => [
            'label' => 'Reacție',
            'plural' => 'Reacții',

            'sections' => [
                'reaction_info' => 'Informații reacție',
                'reaction_info_descr' => 'Detalii despre utilizator și tipul reacției.',
                'target_content' => 'Conținut țintă',
                'target_content_descr' => 'Specifică conținutul la care este atașată reacția.',
            ],

            'fields' => [
                'user_id' => 'Utilizator',
                'reaction_type' => 'Tip reacție',
                'post_id' => 'ID postare',
                'post_comment_id' => 'ID comentariu',
            ],

            'types' => [
                'like' => 'Apreciere',
            ],
        ],

        'user_list' => [
            'label' => 'Listă',
            'plural' => 'Liste',

            'sections' => [
                'list_details' => 'Detalii listă',
                'list_details_descr' => 'Furnizează un nume și un tip pentru această listă de utilizatori.',
                'owner' => 'Proprietar',
                'owner_descr' => 'Selectează utilizatorul care deține această listă.',
            ],

            'fields' => [
                'name' => 'Nume listă',
                'type' => 'Tip listă',
                'user_id' => 'Proprietar listă',
            ],

            'placeholders' => [
                'name' => 'Introdu numele listei',
            ],

            'types' => [
                'blocked' => 'Utilizatori blocați',
                'following' => 'Urmărește',
                'followers' => 'Urmăritori',
                'custom' => 'Listă personalizată',
            ],

            'members' => [
                'title' => 'Vezi membrii listei',
                'breadcrumb' => 'Membri',
                'navigation_label' => 'Vezi membri',
                'fields' => [
                    'id' => 'ID',
                    'username' => 'Utilizator',
                    'created_at' => 'Creat la',
                ],
            ],

        ],

        'user_list_member' => [
            'label' => 'Membru listă',
            'plural' => 'Membri listă',

            'actions' => [
                'create' => 'Adaugă membru',
            ],

            'sections' => [
                'list_association' => 'Asociere listă',
                'list_association_descr' => 'Atribuie un utilizator unei liste specifice.',
            ],

            'fields' => [
                'list_id' => 'ID Listă utilizator',
                'user_id' => 'Utilizator',
            ],

            'placeholders' => [
                'list_id' => 'Selectează o listă',
                'user_id' => 'Selectează un utilizator',
            ],
        ],

        'user_bookmark' => [
            'label' => 'Marcaj',
            'plural' => 'Marcaje',

            'sections' => [
                'bookmark_details' => 'Detalii marcaj',
                'bookmark_details_descr' => 'Asociază un utilizator cu un articol marcat.',
            ],

            'fields' => [
                'user_id' => 'Utilizator',
                'post_id' => 'ID postare',
                'reel_id' => 'ID reel',
                'username' => 'Utilizator',
                'created_at' => 'Creat la',
                'updated_at' => 'Actualizat la',
            ],
        ],

        'user_report' => [
            'label' => 'Raport utilizator',
            'plural' => 'Rapoarte utilizatori',

            'sections' => [
                'reporter_reported' => 'Raportat / raportator',
                'reporter_reported_descr' => 'Identifică utilizatorul care trimite raportul și pe cel raportat.',
                'reported_content' => 'Conținut raportat (opțional)',
                'reported_content_descr' => 'Leagă raportul de o postare, mesaj sau stream specific.',
                'report_details' => 'Detalii raport',
            ],

            'tabs' => [
                'all' => 'Toate',
                'received' => 'Primite',
                'seen' => 'Văzute',
                'solved' => 'Rezolvate',
            ],

            'fields' => [
                'from_user_id' => 'Raportator',
                'user_id' => 'Utilizator raportat',
                'post_id' => 'ID postare',
                'message_id' => 'ID mesaj',
                'stream_id' => 'ID stream',
                'type' => 'Motiv raport',
                'status' => 'Stare',
                'details' => 'Detalii suplimentare',
                'story_id' => 'ID poveste',
                'reel_id' => 'ID reel',
                'reel_comment_id' => 'ID comentariu reel',
            ],

            'types' => [
                'i_dont_like' => 'Nu-mi place',
                'spam' => 'Spam',
                'dmca' => 'DMCA',
                'offensive_content' => 'Conținut ofensator',
                'abuse' => 'Abuz',
            ],

            'statuses' => [
                'received' => 'Primit',
                'seen' => 'Văzut',
                'solved' => 'Rezolvat',
            ],

            'actions' => [
                'view_admin' => 'Deschide conținutul raportat',
                'view_public' => 'Vezi pagina publică',
            ],

            'navigation_badge_tooltip' => 'Numărul rapoartelor deschise trimise de utilizatori',
        ],

        'featured_user' => [
            'label' => 'Utilizator evidențiat',
            'plural' => 'Utilizatori evidențiați',

            'sections' => [
                'main' => 'Evidențiază un utilizator',
                'main_descr' => 'Selectează un utilizator care va fi evidențiat pe platformă.',
            ],

            'fields' => [
                'user_id' => 'Utilizator evidențiat',
                'username' => 'Nume utilizator'
            ],
        ],

        'user_tax' => [
            'label' => 'Informații fiscale',
            'plural' => 'Informații fiscale',

            'sections' => [
                'user' => 'Asociere utilizator',
                'user_descr' => 'Asociază informațiile fiscale unui utilizator și țării emitente.',

                'tax' => 'Identificare fiscală',
                'tax_descr' => 'Detalii legale și de identificare fiscală.',

                'personal' => 'Detalii personale',
                'personal_descr' => 'Informații personale și de adresă suplimentare.',
            ],

            'fields' => [
                'user_id' => 'Utilizator',
                'issuing_country_id' => 'Țara emitentă',
                'legal_name' => 'Nume legal',
                'tax_identification_number' => 'Cod fiscal',
                'vat_number' => 'Cod TVA',
                'tax_type' => 'Tip impozit',
                'date_of_birth' => 'Data nașterii',
                'primary_address' => 'Adresă principală',
                'earnings_ytd' => 'Câștiguri YTD (brut)',
            ],

            'filters' => [
                'min_earnings' => 'Câștig minim',
            ],

            'descriptions' => [
                'primary_address' => 'Introduceți adresa completă',
            ],

            'placeholders' => [
                'user_id' => 'Selectează utilizator',
                'issuing_country_id' => 'Selectează țara',
            ],

            'options' => [
                'types' => [
                    'dac7' => 'DAC7',
                ],
            ],
        ],

        'post_comment' => [
            'label' => 'Comentariu',
            'plural' => 'Comentarii',

            'sections' => [
                'post_comment_details' => 'Detalii comentariu postare',
                'post_comment_details_descr' => 'Detalii despre comentariul la postare.',
            ],

            'fields' => [
                'id' => 'ID',
                'author' => 'Utilizator',
                'message' => 'Mesaj',
                'post_id' => 'Postare'
            ],
        ],

        'attachment' => [
            'label' => 'Atașament',
            'plural' => 'Atașamente',

            'sections' => [
                'file_and_metadata' => 'Fișier & metadate',
                'associations' => 'Asocieri',
                'attachment_details' => 'Detalii atașament',
                'attachment_details_descr' => 'Configurează sau revizuiește detaliile atașamentului.',
            ],

            'fields' => [
                'id' => 'ID',
                'filename' => 'Nume fișier',
                'file' => 'Fișier',
                'driver' => 'Driver de stocare',
                'type' => 'Tip',
                'user_id' => 'Utilizator',
                'post_id' => 'ID postare',
                'message_id' => 'ID mesaj',
                'payment_request_id' => 'ID cerere de depunere',
                'has_thumbnail' => 'Are miniatură',
                'has_blurred_preview' => 'Previzualizare blurată',
                'open' => 'Deschide fișier',
                'story_id' => 'Story',
                'reel_id' => 'Reel',
                'sound_id' => 'Sunet',
                'length'   => 'Durată',
            ],

            'help' => [
                'id' => 'Format UUID preferat.',
                'driver' => 'Selectează driverul de stocare pentru fișierele utilizatorului.',
                'length' => 'Durata fișierului media, exprimată în secunde.',
            ],
        ],

        'poll' => [
            'label' => 'Sondaj',
            'plural' => 'Sondaje',

            'sections' => [
                'post_details' => 'Detalii sondaj',
                'post_details_descr' => 'Configurează detaliile sondajului.',
            ],

            'fields' => [
                'user_id' => 'Utilizator',
                'post_id' => 'ID postare',
                'ends_at' => 'Se încheie la',
                'answer_id' => 'Răspuns selectat',
                'answer' => 'Opțiune',
                'id' => 'ID',
            ],

            'filters' => [
                'poll.id' => 'ID sondaj',
                'user.username' => 'Nume utilizator',
            ],

            'poll_answers' => [
                'poll_choices' => 'Opțiuni sondaj',
                'choices' => 'Opțiuni',
                'actions' => [
                    'create' => 'Adaugă opțiune nouă',
                    'edit' => 'Editează opțiunea',
                    'delete' => 'Șterge opțiunea',
                ],
            ],

            'user_poll_answers' => [
                'label' => 'Răspunsuri utilizator',
                'fields' => [
                    'user_id' => 'Utilizator',
                    'answer_id' => 'Răspuns selectat',
                    'answer' => 'Răspuns',
                ],
                'actions' => [
                    'create' => 'Adaugă răspuns',
                    'edit' => 'Editează răspunsul',
                    'delete' => 'Șterge răspunsul',
                ],
            ],
        ],

        'transaction' => [

            'label' => 'Tranzacție',
            'plural' => 'Tranzacții',

            'sections' => [
                'participants' => 'Participanți',
                'participants_descr' => 'Definește expeditorul și destinatarul implicați în tranzacție.',

                'details' => 'Detalii tranzacție',
                'details_descr' => 'Setează statusul, tipul, furnizorul și datele de bază.',

                'related' => 'Entități aferente',
                'related_descr' => 'Asociază această tranzacție cu conținut sau abonamente.',

                'provider_info' => 'Informații furnizor',
                'provider_info_descr' => 'Adaugă ID-uri sau token-uri opționale de la furnizori externi.',
            ],

            'fields' => [
                'sender_user_id' => 'Expeditor',
                'recipient_user_id' => 'Destinatar',

                'status' => 'Status',
                'type' => 'Tip tranzacție',
                'payment_provider' => 'Furnizor plată',
                'currency' => 'Cod valută',
                'amount' => 'Sumă',
                'taxes' => 'Taxe',

                'subscription_id' => 'Abonament',
                'post_id' => 'Postare',
                'stream_id' => 'Stream',
                'invoice_id' => 'Factură',
                'user_message_id' => 'Mesaj',

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

                'sender' => 'Expeditor',
                'receiver' => 'Destinatar',
                'receiver_user_id' => 'Utilizator destinatar',
                'id' => 'ID'
            ],

            'helpers' => [
                'taxes' => 'Este necesar format JSON. Exemple pot fi luate din tranzacții create de aplicație.',
                'taxes_placeholder' => 'Introduceți detalii despre taxe sau notițe',
            ],

            'status_labels' => [
                'pending' => 'În așteptare',
                'refunded' => 'Rambursat',
                'partially_paid' => 'Parțial plătit',
                'declined' => 'Refuzat',
                'initiated' => 'Inițiat',
                'canceled' => 'Anulat',
                'approved' => 'Aprobat',
            ],

            'type_labels' => [
                'tip' => 'Bacșiș',
                'deposit' => 'Depunere',
                'withdrawal' => 'Retragere',
                'chat_tip' => 'Bacșiș în chat',
                'stream_access' => 'Acces stream',
                'message_unlock' => 'Deblocare mesaj',
                'post_unlock' => 'Deblocare postare',
                'one_month_subscription' => 'Abonament 1 lună',
                'three_months_subscription' => 'Abonament 3 luni',
                'six_months_subscription' => 'Abonament 6 luni',
                'yearly_subscription' => 'Abonament anual',
                'subscription_renewal' => 'Reînnoire abonament',
            ],

            'tabs' => [
                'all' => 'Toate',
                'pending' => 'În așteptare',
                'approved' => 'Aprobat',
                'declined' => 'Respins',
            ],

        ],

        'post' => [
            'label' => 'Postare',
            'plural' => 'Postări',

            'sections' => [
                'details' => 'Detalii postare',
                'details_descr' => 'Configurează detaliile postării.',
                'settings' => 'Setări postare',
                'settings_descr' => 'Setări pentru preț, stare și programare.',
            ],

            'fields' => [
                'user_id' => 'Utilizator',
                'text' => 'Text postare',
                'price' => 'Preț',
                'status' => 'Stare',
                'release_date' => 'Data publicării',
                'expire_date' => 'Data expirării',
                'is_pinned' => 'Fixează această postare',
            ],

            'actions' => [
                'post_url' => 'URL postare',
            ],

            'status_labels' => [
                '0' => 'În așteptare',
                '1' => 'Aprobat',
                '2' => 'Respins',
            ],
        ],

        'hashtag' => [
            'label' => 'Hashtag',
            'plural' => 'Hashtag-uri',

            'sections' => [
                'hashtag_info' => 'Informații hashtag',
                'hashtag_info_descr' => 'Creează și gestionează hashtag-uri folosite în postări și comentarii.',
            ],

            'fields' => [
                'tag' => 'Etichetă',
                'tag_helper' => 'Introdu hashtag-ul fără „#”. Sunt permise doar litere, cifre și underscore (maxim 64). Este salvat cu litere mici.',
            ],
        ],

        'hashtag_link' => [
            'label' => 'Legătură hashtag',
            'plural' => 'Legături hashtag',
            'fields' => [
                'post_id' => 'ID postare',
                'post_comment_id' => 'ID comentariu',
            ],
        ],

        'subscription' => [
            'label' => 'Abonament',
            'plural' => 'Abonamente',

            'sections' => [
                'user_info' => 'Informații utilizator',
                'subscription_details' => 'Detalii abonament',
                'platform_identifiers' => 'Identificatori platformă',
                'timestamps' => 'Marcaje temporale',
            ],

            'fields' => [
                'sender_user_id' => 'Utilizator abonat',
                'recipient_user_id' => 'Utilizator creator',

                'subscriber.username' => 'Nume abonat',
                'creator.username' => 'Nume creator',

                'type' => 'Tip abonament',
                'status' => 'Stare abonament',
                'provider' => 'Procesator plăți',
                'amount' => 'Sumă',

                'paypal_agreement_id' => 'ID acord PayPal',
                'paypal_plan_id' => 'ID plan PayPal',
                'stripe_subscription_id' => 'ID abonament Stripe',
                'ccbill_subscription_id' => 'ID abonament CCBill',
                'verotel_sale_id' => 'ID vânzare Verotel',

                'expires_at' => 'Expiră la',
                'canceled_at' => 'Anulat la',
            ],

            'status_labels' => [
                'active' => 'Activ',
                'completed' => 'Finalizat',
                'canceled' => 'Anulat',
                'suspended' => 'Suspendat',
                'expired' => 'Expirat',
                'failed' => 'Eșuat',
                'pending' => 'În așteptare',
            ],

            'type_labels' => [
                'one_month_subscription' => 'Abonament 1 lună',
                'three_months_subscription' => 'Abonament 3 luni',
                'six_months_subscription' => 'Abonament 6 luni',
                'yearly_subscription' => 'Abonament 1 an',
            ],

            'tabs' => [
                'all' => 'Toate',
                'pending' => 'În așteptare',
                'active' => 'Activ',
                'canceled' => 'Anulat',
            ],
        ],

        'withdrawal' => [
            'label' => 'Retragere',
            'plural' => 'Retrageri',

            'sections' => [
                'details' => 'Detalii retragere',
                'details_descr' => 'Configurează sau revizuiește detaliile cererii de retragere.',
                'payout_summary' => 'Rezumat plată',
                'payout_details' => 'Detalii plată',
                'summary' => 'Rezumatul retragerii',
                'documents' => 'Documente încărcate',
                'processing' => 'Procesare',
                'processing_descr' => 'Revizuiește și actualizează starea retragerii și câmpurile cererii.',
            ],

            'fields' => [
                'id' => 'ID',
                'username' => 'Utilizator',
                'amount' => 'Sumă',
                'requested_amount' => 'Suma solicitată',
                'fee' => 'Comision',
                'net_payout' => 'Sumă de trimis',
                'status' => 'Stare',
                'processed' => 'Procesat',
                'payment_method' => 'Metodă de plată',
                'payout_method_key' => 'Cheie metodă plată',
                'payout_account' => 'Cont de plată salvat',
                'payment_identifier' => 'Identificator plată',
                'stripe_payout_id' => 'ID plată Stripe',
                'stripe_transfer_id' => 'ID transfer Stripe',
                'document' => 'Document',
                'document_field' => 'Scop',
                'file_size' => 'Dimensiune',
                'user_id' => 'Utilizator',
                'message' => 'Mesaj',
                'notes' => 'Note',
                'details_label' => 'Detalii',
                'iban' => 'IBAN',
                'paypal_email' => 'Email PayPal',
                'wallet_address' => 'Adresă portofel',
                'payout_destination' => 'Destinație plată',
                'stripe_account' => 'Cont Stripe',
                'account_label' => 'Etichetă cont',
                'account_holder' => 'Titular cont',
                'swift_bic' => 'SWIFT/BIC',
                'bank' => 'Bancă',
                'bank_address' => 'Adresă bancă',
                'country' => 'Țară',
                'method' => 'Metodă',
            ],

            'helpers' => [
                'stripe_connect_warning' => 'Retragerile prin Stripe Connect pot fi create doar de creatori',
                'status_creation_rule' => 'O retragere nouă trebuie să fie creată cu starea „solicitat”.',
                'processed_warning' => 'Această cerere de retragere a fost deja procesată',
                'reserved_details_locked' => 'Suma, utilizatorul și comisionul nu pot fi schimbate după rezervarea soldului pentru retragere.',
                'amount_overflow' => 'Soldul creditului utilizatorului este mai mic decât suma retragerii. Încercați o sumă mai mică',
                'fees_info' => 'Comisioanele se calculează automat dacă sunt activate în setările de plată.',
                'summary_empty' => 'Rezumatul plății va apărea după ce retragerea este creată.',
                'payout_details_empty' => 'Nu există detalii de plată salvate pentru această retragere.',
                'stored_notes' => 'Notele trimise de utilizator pentru această retragere.',
                'stored_method_reference' => 'Metoda de retragere salvată este afișată doar ca referință.',
                'no_payout_accounts' => 'Adaugă un cont de plată salvat pentru acest utilizator și această metodă înainte de a crea o retragere.',
                'stored_payout_reference' => 'Destinația de plată salvată este afișată doar ca referință.',
                'stored_payout_used' => 'Destinația de plată folosită pentru această retragere.',
                'stripe_payout_reference' => 'Referință payout generată de Stripe.',
                'stripe_transfer_reference' => 'Referință transfer generată de Stripe.',
                'processed_flag' => 'Se setează automat când retragerea este aprobată sau respinsă. Nu poate fi modificată manual.',
            ],

            'status_labels' => [
                'approved' => 'Aprobat',
                'requested' => 'Solicitat',
                'rejected' => 'Respins',
            ],

            'actions' => [
                'approve' => 'Aprobă',
                'reject' => 'Respinge',
            ],

            'tabs' => [
                'all' => 'Toate',
                'requested' => 'Solicitate',
                'approved' => 'Aprobate',
                'rejected' => 'Respinse',
            ],

            'export' => [
                'csv' => 'CSV plăți',
                'gross' => 'Brut',
                'net' => 'Net',
                'method' => 'Metodă',
                'identifier' => 'Identificator',
                'saved_account' => 'Cont salvat',
                'payout_details' => 'Detalii plată',
                'yes' => 'Da',
                'no' => 'Nu',
            ],

            'navigation_badge_tooltip' => 'Numărul de retrageri în așteptare',
        ],

        'payment_request' => [
            'label' => 'Cerere de depunere',
            'plural' => 'Cereri de depunere',
            'navigation_badge_tooltip' => 'Numărul cererilor de depunere în așteptare',

            'sections' => [
                'payment_request' => 'Cerere de depunere',
            ],

            'fields' => [
                'user_id' => 'Utilizator',
                'transaction_id' => 'ID tranzacție',
                'amount' => 'Sumă',
                'status' => 'Stare',
                'type' => 'Tip',
                'reason' => 'Motivul refuzului',
                'message' => 'Mesaj',
            ],

            'status_labels' => [
                'approved' => 'Aprobat',
                'pending' => 'În așteptare',
                'rejected' => 'Respins',
            ],

            'type_labels' => [
                'deposit' => 'Depozit',
            ],

            'actions' => [
                'approve' => 'Aprobă',
                'reject' => 'Respinge',
            ],

            'tabs' => [
                'all' => 'Toate',
                'pending' => 'În așteptare',
                'approved' => 'Aprobat',
                'rejected' => 'Respins',
            ],
        ],

        'invoice' => [
            'label' => 'Factură',
            'plural' => 'Facturi',

            'sections' => [
                'invoice_info' => 'Informații factură',
                'invoice_info_descr' => 'Aici poți vedea datele codificate ale unei facturi generate.',
            ],

            'fields' => [
                'invoice_id' => 'ID factură',
                'transaction_id' => 'ID tranzacție',
                'data' => 'Date',
            ],

            'actions' => [
                'invoice_url' => 'URL factură',
            ],
        ],

        'tax' => [
            'label' => 'Taxă',
            'plural' => 'Taxe',

            'sections' => [
                'details' => 'Detalii taxă',
                'details_descr' => 'Editează detaliile despre taxele platformei.',
            ],

            'fields' => [
                'name' => 'Nume',
                'type' => 'Tip',
                'percentage' => 'Valoare',
                'country_name' => 'Țară',
                'countries_name' => 'Țări',
                'hidden' => 'Ascuns',
            ],

            'type_labels' => [
                'fixed' => 'Fixă',
                'exclusive' => 'Exclusivă',
                'inclusive' => 'Inclusivă',
            ],
        ],

        'country' => [
            'label' => 'Țară',
            'plural' => 'Țări',

            'sections' => [
                'country_details' => 'Detalii Țară',
                'country_details_descr' => 'Detalii despre țară sau regiune.',
            ],

            'fields' => [
                'name' => 'Nume',
                'country_code' => 'Cod Țară',
                'phone_code' => 'Prefix Telefonic',
                'created_at' => 'Creat La',
                'updated_at' => 'Actualizat La',
            ],
        ],

        'stream' => [
            'label' => 'Stream',
            'plural' => 'Streamuri',

            'sections' => [
                'stream_details' => 'Detalii stream',
                'stream_details_descr' => 'Informații de bază despre stream.',
                'stream_source' => 'Sursă și redare stream',
                'stream_source_descr' => 'Configurare pentru livrarea stream-ului și RTMP.',
                'advanced_metadata' => 'Avansat și metadate',
            ],

            'fields' => [
                'name' => 'Nume stream',
                'slug' => 'Slug',
                'price' => 'Preț acces',
                'user_id' => 'Utilizator',
                'poster' => 'Imagine poster',
                'status' => 'Stare',
                'requires_subscription' => 'Necesită abonament',
                'is_public' => 'Stream public',
                'sent_expiring_reminder' => 'Notificare de expirare trimisă',

                'driver' => 'Driver streaming',
                'pushr_id' => 'ID Pushr',
                'rtmp_key' => 'Cheie RTMP',
                'rtmp_server' => 'Server RTMP',
                'hls_link' => 'Link redare HLS',
                'vod_link' => 'Link VOD',

                'settings' => 'Setări stream (JSON)',
                'ended_at' => 'Finalizat la',
                'created_at' => 'Creat la',
                'updated_at' => 'Actualizat la',
            ],

            'status_labels' => [
                'all' => 'Toate',
                'in_progress' => 'În progres',
                'ended' => 'Finalizat',
                'deleted' => 'Șters',
            ],

            'driver_labels' => [
                1 => 'PushrCDN',
                2 => 'LiveKit',
            ],
        ],

        'stream_message' => [
            'label' => 'Mesaj stream',
            'plural' => 'Mesaje stream',

            'sections' => [
                'message_details' => 'Detalii mesaj',
            ],

            'fields' => [
                'user_id' => 'Utilizator',
                'stream_id' => 'Stream',
                'message' => 'Conținut mesaj',
                'created_at' => 'Creat la',
                'updated_at' => 'Actualizat la',
            ],

            'help' => [
                'user_id' => 'Selectează utilizatorul care a trimis mesajul.',
                'stream_id' => 'Alege stream-ul asociat acestui mesaj.',
                'message' => 'Conținutul mesajului din chat.',
            ],
        ],

        'public_page' => [
            'label' => 'Pagină publică',
            'plural' => 'Pagini publice',

            'sections' => [
                'page_details' => 'Detalii pagină',
                'page_details_descr' => 'Configurează conținutul și structura acestei pagini publice.',
                'display_settings' => 'Setări de afișare',
                'display_settings_descr' => 'Controlează modul și locul în care apare această pagină.',
            ],

            'fields' => [
                'title' => 'Titlu',
                'title_helper' => 'Titlul paginii afișat în antet și în listă.',
                'short_title' => 'Titlu scurt',
                'short_title_helper' => 'Titlu alternativ mai scurt pentru navigații sau meniuri.',
                'slug' => 'Slug',
                'content' => 'Continut',
                'slug_helper' => 'Identificator unic folosit în URL (fără spații sau caractere speciale).',
                'shown_in_footer' => 'Afișat în subsol',
                'shown_in_footer_helper' => 'Activează pentru a afișa pagina în subsolul site-ului.',
                'is_tos' => 'Termeni și condiții',
                'is_tos_helper' => 'Activează dacă pagina reprezintă Termenii și Condițiile.',
                'is_privacy' => 'Politica de confidențialitate',
                'is_privacy_helper' => 'Activează dacă pagina este Politica de Confidențialitate.',
                'show_last_update_date' => 'Afișează data ultimei actualizări',
                'show_last_update_date_helper' => 'Dacă este activat, data ultimei modificări va fi afișată pe pagină.',
                'page_order' => 'Ordinea paginilor',
                'page_order_helper' => 'Stabilește ordinea în care apare această pagină în listă.',
                'page_url' => 'URL pagină',
                'destination' => 'Destinație',
                'internal_page' => 'Pagină internă',
                'external_link' => 'Link extern',
                'external_url' => 'URL extern',
                'external_url_helper' => 'Introdu un URL complet http:// sau https://. URL-ul local al paginii va redirecționa aici.',
                'open_in_new_tab' => 'Deschide într-o filă nouă',
            ],
        ],

        'contact_message' => [
            'label' => 'Mesaj contact',
            'plural' => 'Mesaje contact',

            'fields' => [
                'email' => 'Email',
                'subject' => 'Subiect',
                'message' => 'Mesaj',
                'status' => 'Status',
                'is_replied' => 'Răspuns',
                'replied_at' => 'Răspuns la',
                'replied_by' => 'Răspuns de',
                'reply_details' => 'Detalii răspuns',
                'created_at' => 'Creat la',
                'updated_at' => 'Actualizat la',
            ],

            'status' => [
                'pending' => 'În așteptare',
                'replied' => 'Răspuns',
                'unknown_replier' => 'admin necunoscut',
            ],

            'helpers' => [
                'is_replied' => 'Folosește această opțiune după ce răspunzi din clientul de email.',
            ],

            'reply_details' => 'Marcat ca răspuns pe :date de :user.',

            'filters' => [
                'reply_status' => 'Status răspuns',
            ],

            'actions' => [
                'reply_via_email' => 'Răspunde prin email',
                'mark_replied' => 'Marchează ca răspuns',
                'mark_unreplied' => 'Marchează ca în așteptare',
            ],
        ],

        'global_announcement' => [
            'label' => 'Anunț',
            'plural' => 'Anunțuri',

            'fields' => [
                'content' => 'Conținut',
                'size' => 'Dimensiune',
                'expiring_at' => 'Expiră la',
                'is_published' => 'Publicat',
                'is_dismissible' => 'Poate fi închis',
                'is_sticky' => 'Sticky',
                'is_global' => 'Global',
                'id_verified_only' => 'Utilizatori verificați',
            ],

            'helpers' => [
                'is_published' => 'Indică dacă anunțul este vizibil pentru utilizatori.',
                'is_dismissible' => 'Permite utilizatorilor să închidă sau să ascundă acest anunț.',
                'is_sticky' => 'Menține anunțul fixat în partea de sus.',
                'is_global' => 'Afișează anunțul tuturor utilizatorilor din sistem.',
                'id_verified_only' => 'Vizibil doar pentru utilizatorii care și-au verificat identitatea.',
            ],

            'sections' => [
                'content' => 'Conținut',
                'content_descr' => 'Detalii despre anunț.',
                'visibility' => 'Vizibilitate',
                'visibility_descr' => 'Activează/dezactivează comportamentele de afișare.',
            ],

            'size_labels' => [
                'regular' => 'Normal',
                'small' => 'Mic',
            ],
        ],

        'reward' => [
            'label'  => 'Recomandare',
            'plural' => 'Recomandări',

            'sections' => [
                'referral_info'       => 'Informații despre recompensa de recomandare',
                'referral_info_descr' => 'Atribuiți recompensele generate din activitatea de recomandare.',
            ],

            'fields' => [
                'id'                     => 'ID',
                'from_user_id'           => 'Recomandator',
                'to_user_id'             => 'Utilizator recomandat',
                'referral_code_usage_id' => 'Utilizare cod de recomandare',
                'amount'                 => 'Valoarea recompensei',
                'transaction_id'         => 'ID tranzacție',
                'reward_type'            => 'Tipul recompensei',
            ],

            'help' => [
                'reward_type' => 'Codul tipului pentru recompensă.',
            ],
        ],

        'story' => [
            'label'  => 'Story',
            'plural' => 'Stories',

            'sections' => [
                'details'        => 'Detalii story',
                'details_descr'  => 'Informații de bază despre story și proprietar.',
                'settings'       => 'Setări story',
                'settings_descr' => 'Vizibilitate, expirare, linkuri și opțiuni de afișare.',
                'overlay'        => 'Overlay',
                'overlay_descr'  => 'Date overlay (JSON) utilizate în viewer (ex: poziție x/y).',
            ],

            'fields' => [
                'user_id'      => 'Utilizator',
                'mode'         => 'Tip',
                'text'         => 'Text',
                'overlay'      => 'Overlay',
                'bg_preset'    => 'Fundal',
                'is_public'    => 'Public',
                'is_highlight' => 'Evidențiat',
                'expires_at'   => 'Expiră la',
                'sound_id'     => 'Sunet',
                'views'        => 'Vizualizări',
                'link_url'     => 'Link',
                'link_text'    => 'Etichetă link',
            ],

            'mode_labels' => [
                'media' => 'Foto / Video',
                'text'  => 'Text',
            ],

            'help' => [
                'overlay'   => 'Salvat ca JSON (poziție x/y).',
                'sound_id'  => 'Opțional: sunet atașat acestui story.',
                'bg_preset' => 'Se aplică doar pentru story-urile text.',
                'link_url'  => 'Trebuie să înceapă cu http:// sau https://',
                'link_text' => 'Afișat ca buton CTA în viewer.',
            ],

            'actions' => [
                'view_in_app' => 'Vezi în aplicație',
            ],
        ],

        'reel' => [
            'label'  => 'Reel',
            'plural' => 'Reel-uri',

            'sections' => [
                'details'       => 'Detalii reel',
                'details_descr' => 'Informații de bază despre reel și proprietar.',
                'settings'      => 'Setări reel',
                'settings_descr'=> 'Vizibilitate și opțiuni de afișare.',
                'overlay'       => 'Overlay',
                'overlay_descr' => 'Date overlay (JSON) utilizate în viewer.',
            ],

            'fields' => [
                'user_id'   => 'Utilizator',
                'caption'   => 'Descriere',
                'overlay'   => 'Overlay',
                'is_public' => 'Public',
                'sound_id'  => 'Sunet',
                'views'     => 'Vizualizări',
                'comments'  => 'Comentarii',
                'reactions' => 'Reacții',
                'bookmarks' => 'Marcaje',
            ],

            'help' => [
                'overlay'  => 'Salvat ca JSON.',
                'sound_id' => 'Opțional: sunet atașat acestui reel.',
            ],

            'actions' => [
                'view_in_app' => 'Vezi în aplicație',
            ],
        ],

        'reel_comment' => [
            'label'  => 'Comentariu reel',
            'plural' => 'Comentarii reel',

            'fields' => [
                'id'        => 'ID',
                'user_id'   => 'Utilizator',
                'parent_id' => 'Comentariu părinte',
                'message'   => 'Mesaj',
                'reactions' => 'Reacții',
            ],
        ],

        'sound' => [
            'label'  => 'Sunet',
            'plural' => 'Sunete',

            'sections' => [
                'details'        => 'Detalii sunet',
                'details_descr'  => 'Informații de bază despre sunet.',
                'settings'       => 'Setări',
                'settings_descr' => 'Controlul stării și vizibilității sunetului.',
                'media'          => 'Media',
                'media_descr'    => 'Fișiere audio și copertă asociate acestui sunet.',
            ],

            'fields' => [
                'title'       => 'Titlu',
                'artist'      => 'Artist',
                'description' => 'Descriere',
                'is_active'   => 'Activ',
                'cover'       => 'Copertă',
                'audio'       => 'Fișier audio',
                'length'      => 'Durată',
                'attachments' => 'Atașamente'
            ],

            'help' => [
                'title'       => 'Numele afișat al sunetului.',
                'artist'      => 'Artistul sau autorul sunetului.',
                'description' => 'Descriere opțională pentru uz administrativ.',
                'is_active'   => 'Doar sunetele active pot fi selectate în stories și reels.',
                'cover'       => 'Imaginea de copertă afișată în selectorul de sunete.',
                'audio'       => 'Fișierul audio principal asociat acestui sunet.',
            ],

            'validation' => [
                'cover_required' => 'Este necesară o imagine de copertă.',
                'audio_required' => 'Este necesar un fișier audio.',
            ],

            'actions' => [
                'view_attachments' => 'Vezi atașamente',
            ],
        ],


    ],

    'settings_forms' => [
        'security' => [
            'email_domains' => [
                'tab' => 'Domenii email',
                'fields' => [
                    'domain_policy' => 'Politică domenii',
                    'allowedlist_domains' => 'Domenii permise',
                    'blocklist_domains' => 'Domenii blocate',
                ],
                'options' => [
                    'allow_all' => 'Permite toate domeniile',
                    'allowlist_only' => 'Permite doar domeniile din lista permisă',
                    'blocklist_only' => 'Blochează doar domeniile din lista blocată',
                ],
                'helpers' => [
                    'domain_policy' => 'Controlează ce domenii email pot fi folosite la înregistrare.',
                    'allowedlist_domains' => 'Folosit când politica este „Permite doar domeniile din lista permisă”. Introdu domenii precum: example.com (fără schemă).',
                    'blocklist_domains' => 'Folosit când politica este „Blochează doar domeniile din lista blocată”. Introdu domenii precum: bad.com (fără schemă).',
                ],
                'placeholders' => [
                    'domains' => 'Adaugă un domeniu și apasă Enter',
                ],
            ],
            'rate_limits' => [
                'tab' => 'Limite de rată',
                'fields' => [
                    'enable_feature_rate_limits' => 'Activează limitele de rată pentru endpointuri',
                    'enabled' => 'Activat',
                    'max_attempts' => 'Încercări maxime',
                    'window' => 'Fereastră',
                ],
                'helpers' => [
                    'enable_feature_rate_limits' => 'Adaugă limite anti-abuz configurabile din admin pentru acțiunile de creare și generare selectate.',
                    'enabled' => 'Activează sau dezactivează această limită specifică funcției.',
                    'max_attempts' => 'Câte cereri sunt permise în intervalul de timp.',
                    'window' => 'Cât timp se păstrează încercările înainte ca limita să fie resetată.',
                ],
                'units' => [
                    'seconds' => 'secunde',
                ],
                'features' => [
                    'posts_save' => [
                        'title' => 'Salvare postări',
                        'description' => 'Se aplică atunci când utilizatorii creează sau actualizează postări.',
                    ],
                    'posts_comments_add' => [
                        'title' => 'Adăugare comentarii la postări',
                        'description' => 'Se aplică atunci când utilizatorii adaugă comentarii la postări.',
                    ],
                    'stories_store' => [
                        'title' => 'Publicare stories',
                        'description' => 'Se aplică atunci când utilizatorii publică un story.',
                    ],
                    'reels_store' => [
                        'title' => 'Publicare reels',
                        'description' => 'Se aplică atunci când utilizatorii publică un reel.',
                    ],
                    'reels_comments_add' => [
                        'title' => 'Adăugare comentarii la reels',
                        'description' => 'Se aplică atunci când utilizatorii adaugă comentarii la reels.',
                    ],
                    'streams_init' => [
                        'title' => 'Pornire stream',
                        'description' => 'Se aplică atunci când creatorii pornesc un stream nou.',
                    ],
                    'stream_comments_add' => [
                        'title' => 'Comentarii stream',
                        'description' => 'Se aplică atunci când spectatorii trimit mesaje în chatul streamului.',
                    ],
                    'suggestions_generate' => [
                        'title' => 'Generare sugestii AI',
                        'description' => 'Se aplică atunci când utilizatorii generează sugestii cu AI.',
                    ],
                    'profile_asset_generate' => [
                        'title' => 'Asset-uri AI profil',
                        'description' => 'Se aplică atunci când utilizatorii generează imagini avatar sau cover cu AI.',
                    ],
                    'messenger_send' => [
                        'title' => 'Trimitere mesaje',
                        'description' => 'Se aplică atunci când utilizatorii trimit mesaje directe.',
                    ],
                ],
            ],
        ],
        'payments' => [
            'withdrawals' => [
                'fields' => [
                    'manual_payout_methods' => 'Metode manuale de plată',
                    'custom_withdrawal_message' => 'Mesaj personalizat pentru retragere',
                ],
                'helpers' => [
                    'manual_payout_methods' => 'Alege ce metode manuale de plată pot solicita utilizatorii. Etichetele vin din fișierele tale de traducere.',
                    'custom_withdrawal_message' => 'Afișează informații suplimentare lângă formularul de retragere pentru metodele manuale de plată.',
                ],
            ],
        ],
    ],

    'settings' => [
        'general' => 'General',
        'users' => 'Utilitizatori',
        'feed' => 'Feed',
        'media' => 'Media',
        'media_moderation' => 'Moderare media',
        'storage' => 'Stocare',
        'runtime' => 'Runtime',
        'payments' => 'Plăți',
        'websockets' => 'Websockets',
        'emails' => 'Emailuri',
        'streams' => 'Transmisiuni',
        'stories' => 'Stories',
        'reels' => 'Reels',
        'compliance' => 'Conformitate',
        'security' => 'Securitate',
        'referrals' => 'Recomandări',
        'ai' => 'AI',
        'admin' => 'Administrare',
        'theme' => 'Temă',
        'license' => 'Licență',
    ],

];
