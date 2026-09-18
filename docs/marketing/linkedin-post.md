# LinkedIn post

**Image:** `screenshot-report.png`

---

I own a BYD Dolphin Surf, and I wanted a proper energy-efficiency report from the trip data it logs — cost per mile, kWh efficiency, how it stacks up against a petrol equivalent. So I built one: **Surf4Miles**.

I used to do this on my local PC with a custom Go programme and a copy of the data file from the car, but the new version needed to be a website so that it was not tied to a single computer.

The interesting constraint I set myself: it had to work for other owners too, without me ever holding their data. So the architecture is deliberately simple —

- No accounts, no server-side database of anyone's driving history.
- A visitor's cumulative trip data lives only in their own browser (IndexedDB), not on the server.
- Uploads are processed just long enough to calculate the report, then discarded — nothing is written to permanent storage or logged.
- Backup/restore as plain `.db` files, so switching devices doesn't mean losing history.

It's a small PHP 8.3 app, source available under AGPL-3.0 with the Commons Clause (open to inspect and modify, not to resell as a hosted product).

If you drive a Dolphin Surf, it's free to use: https://surf4miles.z-add.co.uk/
Source: https://github.com/StuMP90/suft-miles

#BYD #ElectricVehicles #WebDevelopment #PHP #PrivacyByDesign
