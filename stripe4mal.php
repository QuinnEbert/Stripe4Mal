<?php

// We, obviously, need the Stripe PHP binding:
require_once(dirname(__FILE__)."/stripe-php/lib/Stripe.php");

/** 
 ** !!! ATTENTION ALL EXISTING USERS !!!
 ** 
 ** THIS VERSION OF THE SCRIPT NOW REQUIRES YOU TO PROVIDE BOTH YOUR LIVE "SECRET" AS
 ** WELL AS LIVE "PUBLISHABLE" KEYS!  DO **NOT** WORRY -- YOUR USER'S BROWSER *NEVER*
 ** RECEIVES THE "SECRET" KEY -- IT IS ONLY USED ON THE SERVER TO RETRIEVE THE CHARGE
 ** TOKEN OBJECT FROM STRIPE, IN FACT, THIS IS ACTUALLY A SECURITY ENHANCEMENT AS FAR
 ** AS BOTH YOU AND YOUR USERS SHOULD BE CONCERNED!
 ** 
 ** I greatly thank the users of this script for making this recommendation.  It will
 ** reduce your server's vulnerabilities as far as handling customers' credit card
 ** numbers are concerned (and is still 100% SSL-encrypted all the way down the line
 ** both to your gateway server and to Stripe).
 **/

// set your secret key: remember to change this to your live secret key in production
// see your keys here https://manage.stripe.com/account
$LIVE_SECRET_KEY = "";
// set your public key: remember to change this to your live "publishable" key listed
// on the Stripe "Your account" page
// see your keys here https://manage.stripe.com/account
$LIVE_PUBLIC_KEY = "";

// set your Mals-E.com return URL address here: this is what tells Mal of the charge
// this will probably look something like http://ww5.aitsafe.com/gen/return.htm
// THIS WILL SHOW UP ON THE MAL'S SETUP PAGE WITH A QUESTION MARK AND A FEW OTHER
// THINGS AFTER THAT (LIKE "id" AND "return") -- LEAVE OUT QUESTION MARK & ANYTHING
// AFTER IT WHEN YOU SET THIS SETTING UP -- THE "HOW-TO" EXPLAINS THIS FURTHER...
$URL_UPON_FINISH = "";

// TESTING ONLY -- set this to `true` if you wish to test this without having to go
// through your Mal's cart.  It basically fakes some sample input (a $10 charge and
// fake but-real-looking e-mail address) -- mainly used by me for testing -- but it
// could be useful to somebody else.  This should ALWAYS be set to `false` when you
// are letting customers use it.  Leaving it at `true` on a live e-commerce site is
// a recipe for cancelled orders, refunds, and tragic customer confusion and severe
// dissatisfaction.
$test_mode = false;

/* Set the API key for backend server ops */
Stripe::setApiKey($LIVE_SECRET_KEY);

/* We have a two-step process -- determine what step we're on here */
$user_step = false;
if ((isset($_REQUEST['enter_data']) && $_REQUEST['enter_data']=='yes')
    || ($test_mode &&
        (isset($_REQUEST['enter_data'])==false
            || $_REQUEST['enter_data']!='yes')))
    $user_step = true;
/* FIXME: This is bad form, but since I'm upgrading this to tokenize,
   I'll override requests holding the tokens right now:               */
if (isset($_POST['stripeToken']) && strlen($_POST['stripeToken']))
    $user_step = false;

if (! $test_mode) {
  if (isset($_POST['cc_holder']))
    $pMethod['name'] = $_POST['cc_holder'];
  if (isset($_POST['cc_addrLineOne']))
    $pMethod['address_line1'] = $_POST['cc_addrLineOne'];
  if (isset($_POST['cc_addrLineTwo']))
    $pMethod['address_line2'] = $_POST['cc_addrLineTwo'];
  if (isset($_POST['cc_addrZip']))
    $pMethod['address_zip'] = $_POST['cc_addrZip'];
  if (isset($_POST['cc_addrState']))
    $pMethod['address_state'] = $_POST['cc_addrState'];
  if (isset($_POST['cc_addrCountry']))
    $pMethod['address_country'] = $_POST['cc_addrCountry'];
} else {
  $pMethod['name'] = 'John Doe'; $_POST['cc_holder'] = $pMethod['name'];
  $pMethod['address_line1'] = '123 Any St.'; $_POST['cc_addrLineOne'] = $pMethod['address_line1'];
  $pMethod['address_line2'] = ''; $_POST['cc_addrLineTwo'] = $pMethod['address_line2'];
  $pMethod['address_zip'] = '90210'; $_POST['cc_addrZip'] = $pMethod['address_zip'];
  $pMethod['address_state'] = 'CA'; $_POST['cc_addrState'] = $pMethod['address_state'];
  $pMethod['address_country'] = 'United States'; $_POST['cc_addrCountry'] = $pMethod['address_country'];
}

if ($user_step) {
  if (! $test_mode) {
    // make sure we have the minimum required details available to us here:
    if (!isset($_POST['payTotal'])) {
      header('Content-type: text/plain');
      die('SORRY: Required payment amount not received!');
    }
    if (strstr($_POST['payTotal'],'.') || strstr($_POST['payTotal'],'-') || is_numeric($_POST['payTotal']) == false) {
      header('Content-type: text/plain');
      die('SORRY: Payment amount received not valid!');
    }
    if (!isset($_POST['payEmail'])) {
      header('Content-type: text/plain');
      die('SORRY: Required paying address not received!');
    }
    if (strstr($_POST['payEmail'],'@') == false || strstr(strstr($_POST['payEmail'],'@'),'.') == false) {
      header('Content-type: text/plain');
      die('SORRY: Paying address received not valid!');
    }
  } else {
    $_POST['payTotal'] = '12.34';
    $_POST['payEmail'] = 'somebody@example.com';
  }
  // Boilerplate:
  //header('Content-type: text/html');
?>
<html>
<head>
<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
<title>Pay with Credit Card</title>
<script type="text/javascript" src="https://js.stripe.com/v1/"></script>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
<script type="text/javascript">
  // this identifies your website in the createToken call below
  Stripe.setPublishableKey('<?php echo($LIVE_PUBLIC_KEY); ?>');
  function stripeResponseHandler(status, response) {
    if (response.error) {
      // re-enable the submit button
      $('.submit-button').removeAttr("disabled");
      // show the errors on the form
      $(".payment-errors").html(response.error.message);
    } else {
      var form$ = $("#payment-form");
      // token contains id, last4, and card type
      var token = response['id'];
      // insert the token into the form so it gets submitted to the server
      form$.append("<input type='hidden' name='stripeToken' value='" + token + "' />");
      // and submit
      form$.get(0).submit();
    }
  }
  $(document).ready(function() {
    $("#payment-form").submit(function(event) {
      // disable the submit button to prevent repeated clicks
      $('.submit-button').attr("disabled", "disabled");
      var chargeAmount = <?php echo(intval(($_POST['payTotal']*100))); ?>; //amount you want to charge, in cents. 1000 = $10.00, 2000 = $20.00 ...
      // createToken returns immediately - the supplied callback submits the form if there are no errors
      Stripe.createToken({
        number: $('.card-number').val(),
        cvc: $('.card-cvc').val(),
        exp_month: $('.card-expiry-month').val(),
        exp_year: $('.card-expiry-year').val(),
        name: document.getElementById('cc_holder').value,
        address_line1: document.getElementById('cc_addrLineOne').value,
        address_line2: document.getElementById('cc_addrLineTwo').value,
        address_zip: document.getElementById('cc_addrZip').value,
        address_state: document.getElementById('cc_addrState').value,
        address_country: document.getElementById('cc_addrCountry').value
      }, chargeAmount, stripeResponseHandler);
      return false; // submit from callback
    });
  });
</script>
</head>
<body>
<?php
  echo('<form action="" method="POST" id="payment-form">'."\n");
  // Generate the five fields asking for card information...
  echo("Cardholder&nbsp;name:&nbsp;<input type=\"text\" name=\"cc_holder\" /><br />\n");
  echo("Card&nbsp;number:&nbsp;<input type=\"text\" size=\"20\" autocomplete=\"off\" class=\"card-number\" /><br />\n");
  echo("CVC&nbsp;security&nbsp;code&nbsp;number:&nbsp;<input type=\"text\" size=\"4\" autocomplete=\"off\" class=\"card-cvc\" /><br />\n");
  echo("Expiration&nbsp;month:&nbsp;<select class=\"card-expiry-month\">\n");
  for ($mm = 1 ; $mm <= 12 ; $mm++) {
    echo("<option value=\"$mm\">$mm</option>\n");
  }
  echo("</select><br />\n");
  echo("Expiration&nbsp;year:&nbsp;<select class=\"card-expiry-year\">\n");
  //FIXME: Make the year range smart/adjustable!
  for ($yy = 11 ; $yy <= 25 ; $yy++) {
    echo("<option value=\"20$yy\">20$yy</option>\n");
  }
  echo("</select><br />\n");
  // Hidden form fields to carry data over:
  foreach ($_POST as $key => $value) {
    if (strstr($key,'cc_')==$key || strstr($key,'pay')==$key) {
      if ($key != 'payTotal') {
        if (strstr($key,'pay')!=$key) {
          echo("<input type=\"hidden\" id=\"$key\" name=\"\" class=\"$key\" value=\"".htmlentities($value)."\" />\n");
        } else {
          echo("<input type=\"hidden\" id=\"$key\" name=\"$key\" value=\"".htmlentities($value)."\" />\n");
        }
      } else {
        echo("<input type=\"hidden\" id=\"$key\" name=\"$key\" value=\"".intval(($value * 100))."\" />\n");
      }
    }
  }
  // Customer ID:
  echo("<input type=\"hidden\" name=\"stripeID\" value=\"".htmlentities($_POST['id'])."\" />\n");
  // Payment status:
  echo('<span class="payment-errors"></span><br />');
  // Submit button:
  echo("<input type=\"submit\" name=\"Submit\" value=\"Submit\" />\n");
  // Boilerplate:
  echo("</form>\n");
  echo("</body></html>\n");
} else {
  // create the charge on Stripe's servers - this will charge the user's card
  try {
    $charge = Stripe_Charge::create(array(
      "amount" => $_POST['payTotal'], // amount in cents, again
      "currency" => "usd",
      "card" => $_POST['stripeToken'],
      "description" => 'Stripe payment from '.$_POST['payEmail'])
    );
    //header('Content-type: text/plain');
    //die("TOKEN: {$_POST['stripeToken']} (Charged OK)");
    header('Location: '.$URL_UPON_FINISH.'?id='.$_POST['stripeID'].'&result=1');
  } catch (Exception $e) {
    //header('Content-type: text/plain');
    //die("ERROR: $e");
    header('Location: '.$URL_UPON_FINISH.'?id='.$_POST['stripeID'].'&result=0');
  }
}
