<?php
// <p>BB Login Restyling</p>
// Customize the WordPress login form
function my_custom_login_styles() {
    ?>
    <style type="text/css">

        /* Replace WordPress logo and link to custom site */
        #login h1 a {
            background: url(<?php echo plugin_dir_url( plugin_dir_path( __FILE__ ) ) .'includes/img/bb-logo.svg' ?>) no-repeat center center;
            width: 50px; /* Width to match the standard WordPress logo */
            height: 50px; /* Height to match the standard WordPress logo */
            background-size: contain;
            display: block;
            margin: 0 auto;
        }

        /* Hide the "Remember Me" checkbox */
        .login #loginform .forgetmenot {
            display: none;
        }

		
        .wp-core-ui .button, .wp-core-ui .button-secondary {
            color: #000000;
            border-color: #000000;
        }

        /* Customize the login page background */
        #login {
            background: #fff; /* No background color for the form */
            padding: 30px;
            border-radius: 0px;
            box-shadow: 0px;
            margin-top: 10%;
            max-width: 320px;
            margin: auto;
            position: relative;
            z-index: 1;
        }

        /* Customize the login form */
        #login form {
            padding: 20px;
            border-radius: 0px;
        }

        /* Customize the input fields */
        #login input[type=text],
        #login input[type=password] {
            border: 1px solid #ddd;
            border-radius: 0px;
            padding: 10px;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 10px;
        }
		
		        /* Customize the login button */
        #login input[type=submit] {
            background: #000000; 
            border: none;
            border-radius: 0px;
            color: #ffffff;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        /* Change button color on hover */
        #login input[type=submit]:hover {
            background: #333333;
        }


    </style>
    <?php
}
add_action('login_head', 'my_custom_login_styles');
