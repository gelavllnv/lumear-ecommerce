# Lumear buyer and seller app

Flutter buyer and seller interfaces with a local shared MySQL API in
[`backend/`](backend/README.md). Connected mode uses the imported database
`lumear_ecommerce`. Credentials stay in ignored backend config.

Start the local API and choose **Lumear · Shared MySQL** in VS Code, or run
`scripts/run-connected.ps1 -Device YOUR-ANDROID-DEVICE-ID`. Continue with Google
supports buyers and sellers; new users choose their role, and new shops await
approval. See [local API setup and limitations](backend/README.md).

Buyer browsing and seller product forms share **8 categories and 48 subcategories**.
Choose a main category to filter its subcategories; sellers choose both when
adding or editing a product. The SQL handoff seeds the same hierarchy.

Run `flutter pub get`, then `flutter run`, or select **Lumear · Buyer and seller
preview** in VS Code. No local server, MySQL, Node, or USB API forwarding is needed.

The app starts in demo mode. Select a buyer or seller demo account on the login
screen, then sign in:

| Role | Email | Password |
| --- | --- | --- |
| Buyer | lumear@gmail.com | Lumear123 |
| Seller | seller@lumear.com | Seller123 |

The Create Account address step auto-fills a postal code from the selected
province and city/municipality using a bundled PHLPost lookup. Missing or ambiguous
codes can be entered manually, and changing location clears the old code. The
same form is used for buyers and sellers. Demo registration works locally; real
registration and email verification require the shared backend.

Demo changes stay in memory and reset when the app restarts. They are not shared
between the buyer, seller, and rider apps. You can create a local buyer or seller
account with **Create an account**, then sign in using its email and password.
Local accounts and their initial profile/address remain in memory until app
restart. No email is sent; demo sellers need shop details but no identity uploads.
Duplicate emails (including the two built-in account emails) are rejected.
Some optional features, such as address lookups,
can still use external internet services.

The Flutter API client and connected screens now have a local MySQL adapter.
See [integration notes](INTEGRATION.md). Existing Render/MySQL services and their
data are unaffected by removing their source and configuration from this project.
Previously uploaded seller documents are preserved in the ignored local
`private_uploads/` folder; they are not app assets or part of the handoff.

In Seller > Products, Add/Edit Product supports up to five photos (JPG, PNG,
WEBP, 5 MB each) and one MP4 video (20 MB). Tap an attachment to preview it;
remove attachments in the editor. Attachments stay in the seller demo session
and are not yet published to buyers. The Dark mode switch turns dark mode on
and returns to light mode when turned off; Android remembers the choice.

Run `flutter analyze` and `flutter test` to check the app. Connected tests use a
mock API and do not need a running server or database.

The login screen also includes **Continue with Google**. Android Google sign-in
is prepared for the shared backend; it requires Google OAuth configuration and
the backend token exchange before real accounts can sign in. Follow
[Google sign-in setup](GOOGLE_SIGN_IN.md).

Buyer Profile now includes **My e-wallet** for refund balance, transaction
history and return requests. Google sign-in, forgot password and change password
use email OTP verification. The buyer wallet migration is applied locally and
Seller > Orders > Returns and refunds processes mobile refunds atomically.
Live OTP requires the sender setup in `scripts/configure-email.ps1`; see
[setup and current dependencies](backend/README.md#buyer-refund-wallet-and-account-otp-november-7).

Chat is available from the floating **Chat** button at the bottom-right, above
the buyer or seller navigation. On a product, tap **Chat with seller** to start a
conversation. Buyer notifications open from the bell in the Home header. To try both sides,
send a message as the buyer, sign out, then open Messages as the demo seller.
Demo chat history stays on this running app and clears on restart. Each buyer
sees their own conversations; the built-in demo seller can reply to conversations
about sample shops. A newly registered seller starts with an empty chat inbox.
It never contacts a real seller.

The chat inbox also includes **Lumear AI assistant**. Demo mode uses clearly labeled
sample replies for app help; it does not run an AI model. Connected mode calls
the proposed shared backend assistant endpoint. Live seller messaging and real
AI replies require the backend work described in [integration notes](INTEGRATION.md).
