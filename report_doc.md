# PESO Monthly Report: Guide

This guide explains the monthly report in the PESO Skills Registry: what each row means, where its numbers come from, who uses the report, and why it is laid out the way it is.

It covers two outputs of the same report:

- **PESO Monthly Employment Report (PDF)**: a printable document
- **PESO Monthly Form (Excel)**: a workbook with the same rows and columns

Both outputs contain only the rows the registry can fill: section 2.1 and section B.

---

## 1. Why this report exists

The Public Employment Service Office (PESO) is the local office that helps jobseekers find work, matches them with employers, and refers them to training and livelihood programs. Each month, the PESO has to report what it did to the Department of Labor and Employment (DOLE). The monthly report is how it does that.

The report answers questions like these:

- How many jobseekers registered this month?
- How many of them are men and how many are women?
- How many have registered since the office started counting?
- What skills do registered jobseekers have?

DOLE uses these figures to measure how well each PESO is working, to plan programs, and to decide where to send support. The numbers are also used to compare offices across the province and the country.

---

## 2. Who uses the report and what for

| Who | What they do with it |
|---|---|
| **PESO staff** (officers who handle registration) | Prepare the figures, check them, and keep the records behind them |
| **PESO Manager** | Reviews and signs the report ("Noted by") before it leaves the office |
| **Local Government Unit (LGU)** | Uses the figures to plan local employment programs and report to the local council |
| **DOLE Regional Office and the Bureau of Local Employment (BLE)** | Collects monthly reports from every PESO, tracks performance, and produces national statistics |
| **Auditors and monitoring teams** | Check that the numbers match the source records |

The monthly report is an **official summary**. It does not contain personal details, so it can be shared with DOLE and the LGU. The **applicant list** is different: it includes names and must stay with staff under RA 10173 (the Data Privacy Act).

---

## 3. Key terms

| Term | Meaning |
|---|---|
| **PESO** | Public Employment Service Office. The local employment office for the province or municipality. |
| **DOLE** | Department of Labor and Employment. The national agency that PESOs report to. |
| **BLE** | Bureau of Local Employment. The DOLE bureau that oversees PESOs. |
| **LGU** | Local Government Unit (province, city, or municipality). |
| **Applicant** | A jobseeker who has registered with the PESO. In the system, each one is a record in the `applicants` table. |
| **Registration** | The act of recording a jobseeker in the registry. The date is the `created_at` value in the system. |
| **Regular Program** | The standard PESO services: registering jobseekers, matching them with vacancies, and referring them to employers. It is the PESO's core work, as opposed to the special programs listed below. |
| **SPES** | Special Program for Employment of Students. A DOLE program that gives temporary part-time work to students during their school breaks. |
| **TUPAD** | Tulong Panghanapbuhay sa Ating Disadvantaged/Displaced Workers. A DOLE emergency employment program for workers affected by disasters, closures, or other displacement. |
| **WAP** | A DOLE program listed in the form. Confirm its full name with your PESO office before you publish it, because this guide does not state it. |
| **OFW** | Overseas Filipino Worker. |
| **Returning OFW** | An OFW who has come back to the Philippines and is looking for work. |
| **Retrenched / displaced worker** | A worker who lost a job because of retrenchment (layoffs for business reasons), closure, or a disaster. |
| **Migratory worker** | A worker who moves between places for seasonal or project-based work. |
| **Rural worker** | A worker whose main livelihood is in the countryside, such as farming or fishing. |
| **Wage employment** | Work for a salary or wage, paid by an employer. |
| **Self-employment** | Work the person runs for themselves, such as a vendor or a small business. |
| **PQF** | Philippine Qualifications Framework. The national standard for skill levels, used in the Skills Gap page of the system. |

---

## 4. The form, section by section

The paper form has sections 1 to 4 under heading A, "Public Employment Service". Each section tracks one stage of the PESO's work:

| Section | Stage | Question it answers |
|---|---|---|
| **1. Job Vacancies solicited/reported** | Employers | How many jobs did employers give the PESO? |
| **2. Applicants Registered** | Jobseekers | How many jobseekers registered? |
| **3. Applicants Referred** | Matching | How many jobseekers were sent to an employer or program? |
| **4. Applicants Placed/Assisted** | Outcome | How many were actually hired or assisted? |

Each section breaks down into **programs** (Regular Program, SPES, WAP, TUPAD) and **groups** of jobseekers (returning OFWs, migratory workers, and so on).

**Section B, "Skills of Registered Applicants"**, is not part of the official paper form. It was added to this report because the registry records skills, and the office wanted those figures in the same report. Confirm with your PESO Manager that this section should be included before you send the report to DOLE. If it should not be, it can be removed from the report without changing anything else.

### Why the numbers are arranged in a fixed code system

Each row has a code such as `2.1`, `2.1.1`, or `4.1.4 a`. The codes do three things:

- **They match the official form.** DOLE reads the same row in every PESO's report, so the code must stay the same.
- **They show hierarchy.** `2` is the category, `2.1` is a program inside it, and `2.1.1` is a subtype of that program. The indentation in the report shows the same structure.
- **They make rows easy to refer to.** A staff member can say "row 2.1" and everyone knows which line is meant.

Letters such as `a.` and `b.` under `4.1.4 Training` mark the training levels (Basic and Upgrading).

---

## 5. How to read the columns

The table has two blocks of three columns each:

| Block | Columns | What it counts |
|---|---|---|
| **Current Reporting Period** | Male, Female, Total | Jobseekers who registered **within the dates chosen** (From to To) |
| **Total as of this Period** | Male, Female, Total | Jobseekers who registered **from the beginning up to the end date** |

Some points to note:

- **Male + Female = Total.** If the two sexes don't add up to the total, something is wrong with the data.
- **The "Total as of this Period" column is cumulative.** It grows over time because it includes everyone registered so far, not just this month.
- **The "Current" column is a slice of the cumulative column.** Everyone counted in the current period is also counted in the total column.

DOLE asks for both because the current figure shows the monthly activity, and the cumulative figure shows the size of the registry.

---

## 6. Worked example from the screenshot

The Excel screenshot shows row **2.1 Regular Program** with these values:

| | Male | Female | Total |
|---|---|---|---|
| **Current Reporting Period** | 3 | 3 | 6 |
| **Total as of this Period** | 11 | 9 | 20 |

What this means:

- **6 jobseekers registered in the period** you chose (3 men and 3 women).
- **20 jobseekers have registered in total** up to the end of that period.
- **14 registered before the period started** (20 minus 6). That is the number in the total column that did not come from this month.
- The totals add up: 3 + 3 = 6, 11 + 9 = 20, and 6 is part of 20.

If you change the From and To dates, the first block changes, and the second block changes if the end date moves.

---

## 7. Why the format is built this way

**It follows the official form.** The labels, row codes, column order, and the Male / Female / Total pattern all come from the paper form. Keeping them identical means the office can copy figures across without re-arranging anything.

**It separates "this period" from "to date".** Monthly reports need both. A manager needs to know how busy the month was, and DOLE needs to know how large the registry has become.

**It reports sex in every cell.** DOLE asks for male and female figures so it can track gender balance in employment programs. Totals alone would hide that.

**It uses blank cells for headings and NA for missing data.** The two look similar but mean different things:

- **Blank** means the row is a heading, such as "A. Public Employment Service", and has no figures of its own.
- **NA** means the figure exists on the form, but the registry does not record it.
- **A number, including 0**, means the registry counted it. A zero is a real count of nothing, which is different from NA.

**Only data we have is filled.** The registry records jobseekers and their skills. It does not record vacancies, referrals, placements, SPES or TUPAD participation, or OFW status. Filling those rows with guesses would make the report inaccurate, so they are left as NA (in Excel) or removed (in the PDF).

---

## 8. Row-by-row: what is filled and from where

| Row | Label | Status | Source in the registry |
|---|---|---|---|
| A. | Public Employment Service | Heading | none |
| 1. | Job Vacancies solicited/reported | NA | not recorded (needs a vacancy table) |
| 2. | Applicants Registered | Heading | none |
| **2.1** | **Regular Program** | **Filled** | `applicants` table, counted by `sex` and `created_at` |
| 2.2 | SPES | NA | not recorded |
| 2.3 | WAP | NA | not recorded |
| 2.4 | TUPAD 2020 | NA | not recorded |
| 2.5 | Retrenched/Displaced workers | NA | not recorded as a registration type |
| 2.6 | Returning OFWs | NA | not recorded |
| 2.7 | Migratory Workers | NA | not recorded |
| 2.8 | Rural workers | NA | not recorded |
| 3. | Applicants Referred | NA | not recorded (needs a referral table) |
| 4. | Applicants Placed/Assisted | NA | not recorded (needs a placement table) |
| **B.** | **Skills of Registered Applicants** | **Filled** | `applicant_skill` pivot joined to `skills` |
| B.x | Each skill | Filled | counted once per applicant, split by sex |

### How section 2.1 is calculated

- **Current Reporting Period:** applicants whose `created_at` falls between the From date and the To date, grouped by `sex`.
- **Total as of this Period:** all applicants whose `created_at` is on or before the To date, grouped by `sex`.
- Only **active** applicants are counted (`is_active = true`).
- The barangay, education, skill category, and sex filters on the report screen apply to both blocks.

### How section B is calculated

- Each applicant contributes at most **one count per skill**, even if the skill appears more than once in their record.
- The male, female, and total counts are split the same way as section 2.1.
- Skills are listed alphabetically.

---

## 9. Filters and what they change

| Filter | Effect on the monthly report |
|---|---|
| **Date From** | Start of the current reporting period. If empty, the period starts on the first day of the To month. |
| **Date To** | End of the current period and the cut-off for the total column. If empty, today is used. |
| **Barangay** | Limits both blocks to one barangay. |
| **Education Level** | Limits both blocks to applicants with that highest level. |
| **Skill Category** | Limits both blocks to applicants who have a skill in that category. |
| **Sex** | Limits both blocks to one sex. The sex split then shows zero for the other sex. |

**Export Format** does not apply to the monthly options. The PDF always downloads as PDF, and the monthly form always downloads as Excel.

---

## 10. Which output to use

| Output | Use it for | Contains |
|---|---|---|
| **PESO Monthly Employment Report (PDF)** | Printing, signing, and filing | Section 2.1 and section B only |
| **PESO Monthly Form (Excel)** | Editing, adding figures from other records, and matching the official template | Same rows as the PDF |
| **Applicant Listing (PDF, Excel, CSV)** | Internal review and checking individual records | One row per applicant, including names (keep internal) |

---

## 11. Known issues and open questions

**Header rows in the Excel file.** In the screenshot, the second header row shows "MALE" above both groups, and the "2. Applicants Registered" heading does not appear above row 2.1. This suggests the header rows and the data rows are one line out of step with the formatting. The data values themselves (3, 3, 6, 11, 9, 20) are correct. Send a screenshot of rows 1 to 12 so the header can be fixed.

**Section B is an addition.** It is not on the official form. Confirm with the PESO Manager whether it should go to DOLE.

**Placeholder signatories.** The "Reviewed by" and "Noted by (PESO Manager)" labels are placeholders. Use the titles your office uses.

**Acronyms to verify.** WAP's full name, and the exact wording of section labels such as "TUPAD 2020", should be checked against the current DOLE template.

**Missing data sources.** Sections 1, 3, and 4, and most of section 2, need records the system does not yet hold. These would be a vacancy table (for employers), a referral table (for matching), and a placement table (for outcomes). Until those exist, those rows will stay NA.

---

## 12. Checklist before submitting

1. Set the From and To dates to the exact reporting month.
2. Leave Barangay, Education, Skill Category, and Sex empty for the official report, unless DOLE asked for a filtered version.
3. Download the PDF and check the figures against the applicant listing for the same dates.
4. Confirm whether Section B should be included.
5. Get the PESO Manager's review and signature.
6. Keep the PDF and the matching applicant listing on file for audit.

---

## 13. Glossary of the registry fields used

| Registry field | Where it appears in the report |
|---|---|
| `applicants.sex` | Male / Female columns, sex distribution |
| `applicants.created_at` | Registration date, reporting period, total as of period |
| `applicants.is_active` | Only active records are counted |
| `applicants.barangay_id` | Barangay filter and barangay distribution |
| `applicants.status` | Status distribution in the listing report |
| `applicants.birthdate` | Age and age groups in the listing report |
| `education.highest_level` | Education filter and education distribution |
| `applicant_skill` and `skills.name` | Section B and the skills distribution |
| `applicants.disabilities` | Persons with disability distribution in the listing report |