# Little Haven: Demo Rehearsal Script (about 7 minutes)

## Before you walk on stage (do this once, 10 minutes before)

1. Start XAMPP (Apache + MySQL). Open phpMyAdmin, **Import `database.sql`**. This resets everything to clean demo data.
2. Open `http://localhost/little_havens_daycare/` and log in as the parent once to check it works. If login fails, open `/setup.php` once, then try again.
3. **One browser = one login.** Use separate windows so you never have to log in and out on stage:
   - Window A (normal): **Parent** (new account made live)
   - Window B (private/incognito): **Security**
   - Window C (another browser, or a second private window of a different browser): **Nurse**
   - Window D: **Caregiver**, then **Admin** (log out and in between)
4. All demo accounts use password **password**:
   `nurse@littlehaven.test`, `caregiver@littlehaven.test`, `security@littlehaven.test`, `admin@littlehaven.test`, `parent@littlehaven.test` (Mary Ng'ambi, mother of Chipo).
5. Close other tabs. Zoom the browser to 110-125% so the back row can read it.

## What to say first (30 seconds, no screen)

"Parents who go to work worry whether their child is safe and whether staff know about allergies and medicine. Little Haven is a daycare for ages one to four with a system that records each child's health needs, controls who can collect them, and keeps a log of everything. The system supports trained staff. It does not replace them."

## The demo

**1. Parent signs up and registers a child (Window A, about 1.5 min)**
- Home page, click **Create an account**. Name: *Grace Mulenga*, any email, any phone, password of your choice.
- On "Register a child": Name *Amina Mulenga*, age 2, class *Seedlings*, package *Full Day*, emergency number.
- Allergies: **egg**. Medicine: *Paracetamol*, *5 ml*, time *12:00*, instructions *after lunch*.
- Tick **"Yes, I left it at home"** and **"ask the centre to supply it"**.
- Add an authorised person: *Uncle Sam*, relationship *Uncle*, ID **555/10/1**.
- Say: "The parent records allergies, medicine, dosage, time, and exactly who may collect. If the medicine is left at home, the centre can supply it for an extra fee."
- On the dashboard point out the **red allergy alert** and the **egg warning on today's lunch**.

**2. Security checks children in (Window B, about 45 sec)**
- Log in as security. Check in **Amina** (dropped off by *Grace Mulenga*) and **Chipo** (dropped off by *Mary Ng'ambi*).
- Say: "Every arrival records who dropped the child and the time."

**3. Nurse and medicine (Window C, about 1.5 min)**
- Log in as nurse. Point at **Centre-supplied medicine requests** and press **Confirm** for Amina's Paracetamol.
- Use the **clock box**: set **10:05**. Chipo's Salbutamol shows **DUE NOW** (amber). Set **11:00**: it shows **OVERDUE** (red).
- Press **Mark given**. It moves to "Given today" with the nurse's name and time. Try pressing it again to show it cannot be given twice.
- Under "health notes" type a note for Chipo, for example *Watch for wheezing after play*, and Save.
- Say: "The nurse gives only what the parent wrote. The system never suggests a dose."
- Optional: **Incidents** page, record a minor scrape.

**4. Caregiver (Window D, about 45 sec)**
- Log in as caregiver. Show the checked-in children, the **red allergy banners**, the **menu conflict** (egg in lunch), and Chipo's **nurse note**.
- Press **Log Care**, add a meal: *Ate porridge*.
- Say: "Caregivers see allergy warnings before they serve food."

**5. The stranger at the gate (Window B, about 1 min). This is the highlight.**
- In security, press **Check Out** for **Chipo**. Type ID **000000/00/0**, press **Verify**. The screen shows a big red **NOT AUTHORISED**.
- Now type **123456/10/1** (Chipo's mother), **Verify**, then **Release Child**.
- Say: "The guard compares the person's real ID with the parent's list. A stranger is blocked, and the attempt is recorded."

**6. Fees (Admin, then Parent, about 1 min)**
- Admin: **Fees and packages**, press **Generate November invoices**. Grace's invoice is **K1,050** (Full Day K1,000 plus the K50 medicine fee).
- Parent (Window A): **Payments**, choose a provider, enter a number, **Pay**. A reference number appears.
- Say: "Payments are simulated for the demo. Real Airtel and MTN mobile money would come next."

**7. Parent sees the day, then admin sees everything (about 1 min)**
- Parent dashboard: **timeline**, **AI-assisted care summary**. Say: "This summary is built from the day's records. It only restates facts. It never diagnoses or advises on medicine."
- Admin **Overview**: children present, doses given, the **blocked pickup**, fees collected, the allergy conflicts. Then the **Activity log** shows who did what and when.

## Closing (20 seconds)

"Technology supports trained staff. It does not replace them, and it cannot eliminate abuse or kidnapping, but it makes the right information reach the right person, and it keeps everyone accountable. A real centre also needs approval from the Director of Social Welfare under the Children's Code Act of 2022, sections 268 and 269. Next steps: real mobile money, SMS alerts to parents, and a real AI model for the daily summary."

## Likely questions

- **Is the AI real?** "In this prototype the summary is rule-based, built from recorded facts, so it works offline and cannot invent medical advice. A language model is a planned upgrade, with the same rule: no diagnosis, no doses."
- **How do you stop a stranger?** "Only people the parent listed can collect. The guard checks their real ID against the list. Every attempt, allowed or blocked, is logged."
- **What if a child is allergic to something on the menu?** "The system compares each child's allergies with the day's menu ingredients and warns staff and the parent."
- **How would this make money?** "Monthly package fees, plus extras such as supplying medicine. The prices in the demo are placeholders."
- **Is the payment real?** "No, it is simulated. Nothing is charged."

## If something goes wrong

- **Any page error:** note the message, press back, carry on with the next step. Do not debug on stage.
- **Login fails:** open `/setup.php` once, which resets demo passwords to "password".
- **Everything looks messy after rehearsing:** re-import `database.sql`. It wipes your test sign-ups and gives a clean start. Do this right before presenting.
- **No internet or wifi:** nothing in the system needs it.
