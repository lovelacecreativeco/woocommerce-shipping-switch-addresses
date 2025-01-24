For my WooCommerce store, I was always running into an issue where it would take me 5+ minutes to create a return label by swapping the sender and receiver addresses via Copying and Pasting.

I got fed up with it and decided to start researching how the WooCommerce™ Shipping Plugin is set up to create labels.

To use this PHP script, please copy and paste the contents into your choice of your child theme's functions.php file, or implement it into a code snippets plugin of your choice.

I am using WPCodeBox. Here are my settings:

For "How to run the snippet" I have it set to "Always, on page load"

Hook/Priority: plugins_loaded / 10

Snippet Order: 10

Where to run the snippet: Admin Area

To use the snippet, open up any physical order within WooCommerce. Within the customer Billing and Shipping detail panels, you'll see a button named "Swap Sender & Shipping Addresses"

Once you click this, the page will start to refresh as the AJAX magic does its work behind the scenes. Now, when you go to create a label, you will find that your sending address is now the recipient address. 

Because of how the new WooCommerce Shipping Plugin works, you will need to change and verify the sender address. To do this, click the sender address, and click the newly added Sender Address which will be your customer's address. 

Change a value in there, click verify and save, and continue to purchase your label.

Wayyy less steps than it was before! I hope this helps!

If you have any questions or need assistance with anything, don't hesitate to reach out:

Brian Lovelace
info@lovelacecreative.co
