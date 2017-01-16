PaymentRecall
===============
 
Send a recall mail to order with missed payment.

How to install
--------------

### Download

#### With composer

Require it by adding the following lines to your `composer.json`

```json
"require": {
    "thelia/payment-recall-module": "1.0"
}
```

#### Manually

-   Download the zip archive and extract it
-   Copy the module into `<path-to-thelia>/local/modules/` directory and be sure that the name of the module is `PaymentRecall`

### Activation

-   Go to the modules's list of your Thelia administration panel (with the default URL)
-   Find the *PaymentRecall* module
-   Activate it.

### Configuration

-   Time before recall : this configuration define how long (in minutes) the order must have to be recalled.
-   You can filter orders recalled by their payment module (in general this module is for card payment).

### Usage
You have 2 way to trigger the action who gonna send the mails, firstly a button appears in order edit at modules tab in backoffice this button send a recall mail for the current order.
The second way is a command line ```module:PaymentRecall:CronSendMail``` when this command is called a mail is sent to each order on status "Not paid" and for who the payment module is on enable payment module
and if the time since order has been placed is greater than defined time. We recommend to set up a cron on the command line to automatise this task.




