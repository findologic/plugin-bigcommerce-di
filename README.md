# Findologic BigCommerce DI Plugin



## Development 

### Ngrok

[Ngrok](https://ngrok.com/) provides public urls for Oauth from your local environment.

Only necessary if you have to deal with the authentication of the app and the BigCommerce callback urls.

Start ngrok by pointing it to the port where your local server is running: 

* `ngrok http -subdomain=findologic <PORT>`

Local `<PORT>` gets fowarded to https://findologic.ngrok.io


### Setup

#### BigCommerce Dev Tools

* Login to [developer portal](https://devtools.bigcommerce.com/) and go to `My Apps`.

##### Set callback urls
* Edit the app and go to tab `Technical`.
* Set the Auth Callback URL and Load Callback URL, for development set url from Ngrok.

#### Set app credentials
* On developer portal, click on `View Client ID` of the app.
* Copy `.env-example` to `.env` and set the following environment variables:
  * `DEV_CLIENT_ID=<CLIENT_ID>`
  * `DEV_CLIENT_SECRET=<CLIENT_SECRET>`
  * `DEV_CALLBACK_URL=<URL TO AUTH CALLBACK ENDPOINT>`
