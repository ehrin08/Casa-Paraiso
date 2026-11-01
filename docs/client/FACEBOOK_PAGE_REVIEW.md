---
type: client-research
date: 2026-11-01
source: https://www.facebook.com/profile.php?id=61579320037378
---

# Client Facebook Page Review

A look at the client's public Facebook page, checked against what the Casa Paraiso web app does today. This note is client research only. It does not change project scope. The gaps below are now recorded as Task 8 in [Planned Tasks](../PLANNED_TASKS.md#task-8-align-the-app-with-the-clients-facebook-page).

## How This Was Checked

- Viewed on 2026-11-01 without a Facebook login. Facebook only shows logged-out visitors the page intro and the five newest posts (11 September to 1 November 2026). The About tab, reviews, older posts, Reels, and comments were behind the login wall, so they are not covered here.
- Project facts come from `config/casa.php`, `app/Models/ApplicationSetting.php`, the add-on migration (`database/migrations/2026_07_21_140000_create_addons_table.php`), and [MVP Scope](../MVP_SCOPE.md).

## What the Page Shows

| Item | Facebook page |
| --- | --- |
| Name | Casa Paraiso, "Body Massage and Wellness Spa" (posts also say "Casa Paraiso Body & Wellness Spa") |
| Category | Massage Service, Health Spa |
| Location | Cuta East, Sta. Teresita, Batangas |
| Phone | Listed on the page (an 0991 mobile number) |
| Email | Listed on the page (a Gmail address) |
| Followers | 821 |
| Reviews | Not yet rated (0 reviews) |
| Hours | "Open daily from 1PM to 11PM" (repeated in several posts) |
| Price | "Price starts at 299!" |
| How to book | "Send us a direct message for reservation!" and "Visit Casa Paraiso today or book an appointment" |
| Language | English, with some Tagalog (for example, thanking "solid clients") |

### Recent Posts

| Date | Post |
| --- | --- |
| 1 Nov 2026 | Holiday advisory: closed 1 and 2 November for All Saints' and All Souls' Day, back on 3 November, 1:00 PM to 11:00 PM. |
| 14 Oct 2026 | Thanks loyal customers; open daily 1PM to 11PM; price starts at 299; message for reservations. Photo set of customers. |
| 1 Oct 2026 | Teachers' Month promo: PHP 50 off for teaching and non-teaching staff, 1 to 31 October 2026, 1:00 PM to 11:00 PM. |
| 21 Sep 2026 | Same open-hours and price message with a photo set. |
| 11 Sep 2026 | "Happy Friday" thanks to regular clients; book or walk in; open 1PM to 11PM. |

## Alignment With the Project

### Matches

- **Brand and location.** The app's default address is Barangay Cuta East, Santa Teresita, Batangas, and the map link points to the same spa.
- **Facebook and Messenger links.** The app's default `facebook_url` and `messenger_url` use this page's ID (61579320037378), and the landing page shows a "Visit Facebook" link.
- **Opening time and every-day schedule.** Both open daily at 1:00 PM.
- **Loyalty focus.** The posts keep thanking "loyal" and "solid" clients. This fits the RFM rewards, Gold and Platinum memberships, and gift vouchers in the app.
- **Feedback.** The page has no reviews yet. The app's post-service feedback prompt can collect the reviews the page is missing.

### Gaps To Confirm With the Client

1. **Closing time.** The page says the spa closes at 11:00 PM. The app books until midnight (`closes_at` `00:00` in `config/casa.php`, and "1:00 PM to 12:00 MN" in [MVP Scope](../MVP_SCOPE.md)). If 11:00 PM is right, customers can book slots after the spa has closed, and a 2-hour AURORA BREEZE could start as late as 10:00 PM in the app but would need to start by 9:00 PM. Ask the client which closing time is correct.
2. **"Starts at 299".** In the app, PHP 299 is the 30-Minute Back Massage, and it is only an add-on. The cheapest package you can book alone is GAIA TOUCH at PHP 499. If walk-in or message customers can buy the 299 back massage by itself, the app has no way to book it on its own. Ask whether it should be a bookable service.
3. **Holiday closures.** The spa closed on 1 and 2 November. The app has no closed-date or holiday setting, so customers could still book those days unless staff remove every therapist's availability by hand. A simple "closed dates" setting would cover this.
4. **Seasonal promos.** The Teachers' Month promo was PHP 50 off for a group of customers for one month. The app's discounts come from membership tiers and RFM rules, not from dated promos for a customer group. Staff would need to record it some other way. Ask how often they run promos like this before building anything.
5. **Contact details.** The page lists a phone number and email, but the app's defaults leave `contact_phone` and `contact_email` empty. The admin should enter them in Settings so the website matches the page. (They are not copied here.)
6. **How bookings come in today.** Reservations happen through Messenger DMs now. The website should be shared on the page (for example, a "Book now" button or a pinned post linking to casaparaiso.site) so customers move to online booking. That is a client action, not code.
7. **Business name.** The page and posts use "Casa Paraiso Body & Wellness Spa" and "Body Massage and Wellness Spa". The MVP Scope uses "Casa Paraiso - Body and Wellness Spa". These are close, but the public name in Settings should match the client's preferred spelling.

## Suggested Questions for the Client

- Is closing time 11:00 PM or midnight, and what is the latest start time you accept?
- Can customers book the PHP 299 back massage on its own?
- Do you want to mark holiday closures in the system yourselves?
- How often do you run promos like the Teachers' Month discount?
- Can you add the website link to the Facebook page and Messenger auto-reply?
