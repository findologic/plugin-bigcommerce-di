# Findologic BigCommerce DI Plugin



## Development 

### Ngrok

[Ngrok](https://ngrok.com/) provides you public urls for Oauth from your local environment.
Login with shared account from Keepass and follow install instructions.

Start ngrok by pointing it to the port where your local server is running: 

* `ngrok http -subdomain=findologic <PORT>`

Local `<PORT>` gets fowarded to https://findologic.ngrok.io

### Set callback urls
* Login to [developer portal](https://devtools.bigcommerce.com/) and go to `My Apps`.


### Set credentials
* Login to [developer portal](https://devtools.bigcommerce.com/) and go to `My Apps`.
* Click on `View Client ID`.
* Copy `.env-example` to `.env` and set the following environment variables:
  * `DEV_CLIENT_ID=<CLIENT_ID>`
  * `DEV_CLIENT_SECRET=<CLIENT_SECRET>`
  * `DEV_CALLBACK_URL=<URL TO AUTH CALLBACK ENDPOINT>`
