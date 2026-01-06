<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">

    <meta name=mobile-web-app-capable content=yes>
    <meta name=apple-mobile-web-app-capable content=yes>
    <meta name=apple-mobile-web-app-status-bar-style content=black-translucent>
    <meta http-equiv=X-UA-Compatible content="ie=edge">
    <meta http-equiv=Content-Security-Policy content=upgrade-insecure-requests>

    @php $setting = \Helper::getSetting() @endphp
    @if (!empty($setting['software_favicon']))
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('/storage/' . $setting['software_favicon']) }}">
    @endif

    <link rel="stylesheet" href="{{ asset('assets/css/fontawesome.min.css') }}">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700&family=Roboto+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,100&display=swap"
        rel="stylesheet">
    <title>{{ env('APP_NAME') }}</title>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php $custom = \Helper::getCustom() @endphp
    <style>
        body {
            font-family: {{ $custom['font_family_default'] ?? "'Roboto Condensed', sans-serif" }};
        }

        :root {
            --ci-primary-color: {{ $custom['primary_color'] }};
            --ci-primary-opacity-color: {{ $custom['primary_opacity_color'] }};
            --ci-secundary-color: {{ $custom['secundary_color'] }};
            --ci-gray-dark: {{ $custom['gray_dark_color'] }};
            --ci-gray-light: {{ $custom['gray_light_color'] }};
            --ci-gray-medium: {{ $custom['gray_medium_color'] }};
            --ci-gray-over: {{ $custom['gray_over_color'] }};
            --title-color: {{ $custom['title_color'] }};
            --text-color: {{ $custom['text_color'] }};
            --value-color-jackpot: {{ $custom['value_color_jackpot'] }};
            --value-color-navtop: {{ $custom['value_wallet_navtop'] }};
            --bonus-color-dep: {{ $custom['bonus_color_dep'] }};
            --sub-text-color: {{ $custom['sub_text_color'] }};
            --text-sub-color: {{ $custom['text_sub_color'] }};
            --back-sub-color: {{ $custom['back_sub_color'] }};
            --item-sub-color: {{ $custom['item_sub_color'] }};
            --title-sub-color: {{ $custom['title_sub_color'] }};
            --placeholder-color: {{ $custom['placeholder_color'] }};
            --placeholder-background: {{ $custom['placeholder_background'] }};
            --text-color-footer: {{ $custom['text_color_footer'] }};
            --opacity-bottom-nav: {{ $custom['opacity_bottom_nav'] }};
            --opacity-categories: {{ $custom['opacity_categories'] }};
            --card-transaction: {{ $custom['card_transaction'] }};
            --values-deposit: {{ $custom['colors_deposit_value'] }};
            --colors-players: {{ $custom['color_players'] }};
            --standard-color: #1C1E22;
            --shadow-color: #111415;
            --page-shadow: linear-gradient(to right, #111415, rgba(17, 20, 21, 0));
            --autofill-color: #f5f6f7;
            --yellow-color: #FFBF39;
            --yellow-dark-color: #d7a026;
            --border-radius: {{ $custom['border_radius'] }};
            --tw-border-spacing-x: 0;
            --tw-border-spacing-y: 0;
            --tw-translate-x: 0;
            --tw-translate-y: 0;
            --tw-rotate: 0;
            --tw-skew-x: 0;
            --tw-skew-y: 0;
            --tw-scale-x: 1;
            --tw-scale-y: 1;
            --tw-scroll-snap-strictness: proximity;
            --tw-ring-offset-width: 0px;
            --tw-ring-offset-color: #fff;
            --tw-ring-color: rgba(59, 130, 246, .5);
            --tw-ring-offset-shadow: 0 0 #0000;
            --tw-ring-shadow: 0 0 #0000;
            --tw-shadow: 0 0 #0000;
            --tw-shadow-colored: 0 0 #0000;

            --botao-deposito-text-nav: {{ $custom['botao_deposito_text_nav'] }};
            --botao-login-text-nav: {{ $custom['botao_login_text_nav'] }};
            --botao-registro-text-nav: {{ $custom['botao_registro_text_nav'] }};
            --botao-login-text-modal: {{ $custom['botao_login_text_modal'] }};
            --botao-registro-text-modal: {{ $custom['botao_registro_text_modal'] }};

            --border-registro-nav: {{ $custom['botao_registro_border_nav'] }};
            --border-login-nav: {{ $custom['botao_login_border_nav'] }};
            --border-deposito-nav: {{ $custom['botao_deposito_border_nav'] }};
            --text-opacity: {{ $custom['text_opacity'] }};
            --background-color-category: {{ $custom['background_color_category'] }};
            --background-color-jackpot: {{ $custom['background_color_jackpot'] }};


            --botao-deposito-text-dep: {{ $custom['botao_deposito_text_dep'] }};
            --botao-deposito-background-dep: {{ $custom['botao_deposito_background_dep'] }};
            --botao-deposito-border-dep: {{ $custom['botao_deposito_border_dep'] }};

            --botao-deposito-text-saq: {{ $custom['botao_deposito_text_saq'] }};
            --botao-deposito-background-saq: {{ $custom['botao_deposito_background_saq'] }};
            --botao-deposito-border-saq: {{ $custom['botao_deposito_border_saq'] }};



            /* Adicionando unidades apropriadas */
            --invert-config: {{ $custom['invert_percentage'] }}%;
            --sepia-config: {{ $custom['sepia_percentage'] }}%;
            --saturate-config: {{ $custom['saturate_percentage'] }}%;
            --hue-rotate-config: {{ $custom['hue_rotate_deg'] }}deg;
            --brightness-config: {{ $custom['brightness_percentage'] }}%;
            --contrast-config: {{ $custom['contrast_percentage'] }}%;

            --input-primary: {{ $custom['input_primary'] }};
            --input-primary-dark: {{ $custom['input_primary_dark'] }};

            --carousel-banners: {{ $custom['carousel_banners'] }};
            --carousel-banners-dark: {{ $custom['carousel_banners_dark'] }};


            --sidebar-color: {{ $custom['sidebar_color'] }} !important;
            --sidebar-color-dark: {{ $custom['sidebar_color_dark'] }} !important;

            --color-bt-1: {{ $custom['color_bt_1'] }} !important;
            --color-bt-1-dark: {{ $custom['color_bt_1_dark'] }} !important;

            --color-bt-2: {{ $custom['color_bt_2'] }} !important;
            --color-bt-2-dark: {{ $custom['color_bt_2_dark'] }} !important;

            --color-bt-3: {{ $custom['color_bt_3'] }} !important;
            --color-bt-3-dark: {{ $custom['color_bt_3_dark'] }} !important;

            --color-bt-4: {{ $custom['color_bt_4'] }} !important;
            --color-bt-4-dark: {{ $custom['color_bt_4_dark'] }} !important;

            --background-bottom-navigation: {{ $custom['background_bottom_navigation'] }} !important;
            --background-bottom-navigation-dark: {{ $custom['background_bottom_navigation_dark'] }} !important;

            --background-color-cassino: {{ $custom['background_color_cassino'] }} !important;
            --background-color-cassino-dark: {{ $custom['background_color_cassino_dark'] }} !important;

            --background-itens-selected: {{ $custom['Border_bottons_and_selected'] }} !important;
            --background-itens-selected-dark: {{ $custom['Border_bottons_and_selected_dark'] }} !important;

            --borders-and-dividers-colors: {{ $custom['borders_and_dividers_colors'] }} !important;
            --borders-and-dividers-colors-dark: {{ $custom['borders_and_dividers_colors_dark'] }} !important;

            --search-back: {{ $custom['search_back'] }} !important;
            --search-back-dark: {{ $custom['search_back_dark'] }} !important;


            --botao-deposito-background-nav: {{ $custom['botao_deposito_background_nav'] }} !important;
            --botao-deposito-background-nav-dark: {{ $custom['botao_deposito_background_nav_dark'] }} !important;

            --botao-login-background-nav: {{ $custom['botao_login_background_nav'] }} !important;
            --botao-login-background-nav-dark: {{ $custom['botao_login_background_nav_dark'] }} !important;

            --botao-registro-background-nav: {{ $custom['botao_registro_background_nav'] }} !important;
            --botao-registro-background-nav-dark: {{ $custom['botao_registro_background_nav_dark'] }} !important;

            --botao-login-background-modal: {{ $custom['botao_login_background_modal'] }} !important;
            --botao-login-background-modal-dark: {{ $custom['botao_login_background_modal_dark'] }} !important;

            --botao-registro-background-modal: {{ $custom['botao_registro_background_modal'] }} !important;
            --botao-registro-background-modal-dark: {{ $custom['botao_registro_background_modal_dark'] }} !important;




            --navtop-color {{ $custom['navtop_color'] }};
            --navtop-color-dark: {{ $custom['navtop_color_dark'] }};


            --side-menu {{ $custom['side_menu'] }};
            --side-menu-dark: {{ $custom['side_menu_dark'] }};

            --footer-color {{ $custom['footer_color'] }};
            --footer-color-dark: {{ $custom['footer_color_dark'] }};

            --card-color {{ $custom['card_color'] }};
            --card-color-dark: {{ $custom['card_color_dark'] }};

            --transaction-background: {{ $custom['transaction_item_background'] }};



        }

        .navtop-color {
            background-color: {{ $custom['navtop_color'] }} !important;
        }

        :is(.dark .navtop-color) {
            background-color: {{ $custom['navtop_color_dark'] }} !important;
        }

        .background-bottom-navigation {
            background-color: {{ $custom['background_bottom_navigation'] }} !important;
        }

        :is(.dark .background-bottom-navigation) {
            background-color: {{ $custom['background_bottom_navigation_dark'] }} !important;
        }

        .bg-base {
            background-color: {{ $custom['background_base'] }};
        }

        :is(.dark .bg-base) {
            background-color: {{ $custom['background_base_dark'] }};
        }

        .background-color-cassino {
            background-color: {{ $custom['background_color_cassino'] }};
        }

        :is(.dark .background-color-cassino) {
            background-color: {{ $custom['background_color_cassino_dark'] }};
        }

        .background-itens-selected {
            background-color: {{ $custom['Border_bottons_and_selected'] }};
        }

        :is(.dark .background-itens-selected) {
            background-color: {{ $custom['Border_bottons_and_selected_dark'] }};
        }
    </style>

    @if (!empty($custom['custom_css']))
        <style>
            {!! $custom['custom_css'] !!}
        </style>
    @endif

    @if (!empty($custom['custom_header']))
        {!! $custom['custom_header'] !!}
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])



    <script>
        //         document.addEventListener('contextmenu', function(e) {
        //            e.preventDefault();
        //        });
        //

        //        document.addEventListener('keydown', function(e) {
        //           if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I') || (e.ctrlKey && e.key === 'U')) {
        //                e.preventDefault();
        //           }
        //       });

        //
    </script>

</head>

<body color-theme="dark" class="bg-base text-gray-800 dark:text-gray-300 ">
    <div id="infinitysoft"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.0.0/datepicker.min.js"></script>
    <script>
        window.Livewire?.on('copiado', (texto) => {
            navigator.clipboard.writeText(texto).then(() => {
                Livewire.emit('copiado');
            });
        });

        window._token = '{{ csrf_token() }}';
        //if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        if (localStorage.getItem('color-theme') === 'light') {
            document.documentElement.classList.remove('dark')
            document.documentElement.classList.add('light');
        } else {
            document.documentElement.classList.remove('light')
            document.documentElement.classList.add('dark')
        }
    </script>

    @if (!empty($custom['custom_js']))
        <script>
            {!! $custom['custom_js'] !!}
        </script>
    @endif

    @if (!empty($custom['custom_body']))
        {!! $custom['custom_body'] !!}
    @endif

    @if (!empty($custom))
        <script>
            const custom = {!! json_encode($custom) !!};
        </script>
    @endif

</body>

</html>
