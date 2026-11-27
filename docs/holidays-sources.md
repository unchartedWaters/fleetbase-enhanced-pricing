# Holiday data sources

What each jurisdiction's holidays were checked against, written by the research that built the datasets. Items marked unconfirmed were left out.

## US-sources

### US holiday dataset sources (fetched 2026-11-27)

Criterion: a day is included when an official state source (statute, or state HR/administration holiday schedule) shows it as a state-government day off / legal state holiday in that state. Single-year schedules were accepted only for days that are standing policy rather than one-off orders (see "unconfirmed"). `since` for Juneteenth is 2021 (federal); individual states adopted it later (2022-2023); the dataset does not model per-state start years.
Federal baseline: 5 U.S.C. 6103 (federal holidays; Saturday -> Friday, Sunday -> Monday, 6103(b)); OPM page not re-fetched (OPM was not fetched in this session; the rule is quoted from 6103(b) from memory, treated as known law). Observed rule is encoded per holiday from the three spec policies; states whose rule differs are listed under "observed-rule deviations" at the bottom.
Note: statute text for MS, NJ, SD, WY was read via Justia mirrors (not the legislature site); AL via the Alabama court-system 2026 calendar.

## Per state (URL: what it confirmed)
AL: https://www.alacourt.gov/docs/2026UJSCalendar.pdf : NYD, MLK/Lee (3rd Mon Jan), Washington/Jefferson (Feb), Confederate Memorial Day (Apr 27 2026 = 4th Mon), Memorial, Jefferson Davis Birthday (Jun 1 = 1st Mon), Juneteenth, Independence Day (obs Jul 3), Labor, Columbus Day, Veterans, Thanksgiving, Christmas. Mardi Gras only Baldwin/Mobile counties (excluded).
AK: https://doa.alaska.gov/dof/payroll/resource/calendar2026.pdf : NYD, MLK, Presidents, Seward's Day (Mar 30), Memorial, Juneteenth, Jul 4 (obs Jul 3), Labor, Alaska Day Oct 18 (obs Oct 19), Veterans, Thanksgiving, Christmas. No Columbus/Indigenous Peoples' Day on state schedule.
AZ: https://www.azleg.gov/ars/1/00301.htm : ARS 1-301 list (NYD, MLK/Civil Rights, Presidents, Memorial, Jul 4, Labor, Columbus, Veterans, Thanksgiving, Christmas). No Juneteenth in statute.
AR: https://sas.arkansas.gov/wp-content/uploads/52-Holidays-and-Birthday-Leave-5.23.2025.pdf : NYD, MLK, Washington/Daisy Gatson Bates, Memorial, Jul 4, Labor, Veterans, Thanksgiving, Christmas Eve, Christmas; Sat->Fri, Sun->Mon. No Columbus, no Juneteenth.
CA: https://www.calhr.ca.gov/about-calhr/divisions-programs/personnel-management/leave-benefits/state-holidays/ : 2026 state holidays NYD, MLK, Presidents, Cesar Chavez (Mar 31), Memorial, Jul 4, Labor, Veterans, Thanksgiving, Day after Thanksgiving, Christmas. Saturday = holiday credit, Sunday -> Monday. Gov Code 6700 (https://california.public.law/codes/government_code_section_6700) enumerates more days (Juneteenth, Lincoln, Columbus, Admission, Native American Day...) but they are not on the CalHR state-employee day-off schedule; left out.
CO: https://dhr.colorado.gov/sites/dhr/files/documents/Calendar%20Year%202026%20Holiday%20Schedule.pdf : NYD, MLK, Presidents, Memorial, Juneteenth, Independence (obs Jul 3), Labor, Cabrini Day (Oct 5), Veterans, Thanksgiving, Christmas. (C.R.S. 24-11-101 page not fetched.)
CT: https://portal.ct.gov/About/Legal-State-Holidays : statutory NYD, MLK, Lincoln's Birthday (Feb 12), Washington's Birthday, Memorial, Juneteenth, Jul 4, Labor, Columbus, Veterans, Christmas; Good Friday and Thanksgiving governor-designated; Sat->Fri, Sun->Mon.
DE: https://dhr.delaware.gov/holidays/2026.shtml : NYD, MLK, Good Friday, Memorial, Juneteenth, Jul 4 (Jul 3), Labor, Election Day, Return Day (Sussex, even yrs), Veterans, Thanksgiving, Day after Thanksgiving, Christmas. No Presidents' Day.
DC: https://code.dccouncil.us/us/dc/council/code/sections/1-612.02 : D.C. Code 1-612.02: MLK, Washington's, Memorial, Juneteenth, Jul 4, Labor, Indigenous Peoples' Day, Veterans, Thanksgiving, Christmas, Emancipation Day Apr 16, Inauguration Day (every 4th yr, not modeled); Sat->Fri, Sun->Mon.
FL: https://www.flsenate.gov/Laws/Statutes/2025/110.117 : Fla. Stat. 110.117: NYD, MLK, Memorial, Jul 4, Labor, Veterans, Thanksgiving, Friday after Thanksgiving, Christmas; Sat->Fri, Sun->Mon. No Presidents', Columbus, Juneteenth.
GA: https://georgia.gov/georgia-state-holidays-2026 : NYD, MLK, Memorial, Juneteenth, Jul 4 (Jul 3), Labor, Columbus, Veterans, Thanksgiving, Christmas. Three floating "State Holiday"/Washington's Birthday days (Apr 3, Nov 27, Dec 24 in 2026) are set annually -> unconfirmed as rules, left out; no Feb Presidents' Day.
HI: https://data.capitol.hawaii.gov/hrscurrent/Vol01_Ch0001-0042F/HRS0008/HRS_0008-0001.htm (HRS 8-1) + https://dhrd.hawaii.gov/wp-content/uploads/2025/01/State-Holidays-2026-2027-1.pdf : NYD, MLK, Presidents, Kuhio Day Mar 26, Good Friday, Memorial, Kamehameha Jun 11, Jul 4, Statehood Day (3rd Fri Aug), Labor, Veterans, Thanksgiving, Christmas, General Election Day (even yrs). No Juneteenth/Columbus.
ID: https://legislature.idaho.gov/statutesrules/idstat/Title73/T73CH1/SECT73-108/ : Idaho Code 73-108: NYD, MLK-Idaho Human Rights Day, Washington's, Memorial, Jul 4, Labor, Columbus, Veterans, Thanksgiving, Christmas; Sat->Fri, Sun->Mon. No Juneteenth.
IL: https://illinois.gov/content/dam/soi/en/web/irb/documents/state-holidays.pdf : NYD, MLK, Lincoln's Birthday, Washington's, Memorial, Juneteenth, Jul 4, Labor, Columbus, General Election Day (even yrs), Veterans, Thanksgiving + Friday, Christmas. Memo is dated 2022/2023 (old); Pulaski Day not on it.
IN: https://secure.in.gov/spd/benefits/files/2026-Holidays-Memorandum.pdf : 2026: NYD, MLK, Good Friday, Primary Election Day, Memorial, Jul 4, Labor, Columbus, General Election Day, Veterans, Thanksgiving, "Lincoln's Birthday" (Nov 27), "Washington's Birthday" (Dec 24), Christmas. No Juneteenth, no Feb Presidents' Day.
IA: https://www.legis.iowa.gov/docs/code/2024/1C.pdf : Iowa Code 1C.2 paid holidays: NYD, MLK, Memorial, Jul 4, Labor, Veterans, Thanksgiving, Friday after, Christmas; Sat->Fri, Sun->Mon. (1C.1 legal-holiday list also has Lincoln/Washington, not paid.)
KS: https://admin.ks.gov/offices/personnel-services/agency-information/holidays-for-state-of-kansas-executive-branch-employees/holidays-for-2026 : 2026: NYD, MLK, Memorial, Juneteenth, Jul 3, Labor, Veterans, Thanksgiving, Nov 27, Dec 24, Dec 25. Presidents'/Columbus absent.
KY: https://extranet.personnel.ky.gov/Pages/Leave.aspx + https://apps.legislature.ky.gov/LAW/STATUTES/statute.aspx?id=1386 : statute: NYD (+1 day), MLK, Good Friday (half day), Memorial, Jul 4, Labor, Veterans, presidential election day, Thanksgiving (+1), Christmas (+1); extra days governor-designated. 2026 page adds Juneteenth. No Presidents'/Columbus.
LA: https://civilservice.louisiana.gov/files/HRHandbook/JobAid/11-Understanding State Holidays.pdf : La. R.S. 1:55: NYD, MLK, Mardi Gras, Good Friday, Jul 4, Labor, Veterans, Thanksgiving, Christmas, Inauguration/Election Day (even yrs). Memorial/Washington's/Presidents' only by governor proclamation (Memorial included, see unconfirmed). Columbus only Sulphur city court. Juneteenth not in statute.
ME: https://www.maine.gov/bhr/state-employees/holiday-schedule + https://www.legislature.maine.gov/legis/statutes/4/title4sec1051-1.html : NYD, MLK, Washington's, Patriots' Day, Memorial, Juneteenth, Jul 4 (Jul 3), Labor, Indigenous Peoples' Day, Veterans, Thanksgiving, Thanksgiving Friday, Christmas.
MD: https://dbm.maryland.gov/employees/Pages/StateHolidays2026.aspx : NYD, MLK, Presidents, Memorial, Juneteenth, Jul 4, Labor, Columbus, Election Day (even yrs), Veterans, Thanksgiving, American Indian Heritage Day (Fri after), Christmas; Sat->Fri, Sun->Mon.
MA: https://www.sec.state.ma.us/divisions/cis/legal-holidays.htm : NYD, MLK, Washington's, Patriots' Day, Memorial, Juneteenth, Jul 4, Labor, Columbus, Veterans, Thanksgiving, Christmas. Evacuation Day/Bunker Hill Day are Suffolk County only (excluded). Sunday->Monday only.
MI: https://michigan.gov/som/government/state-holidays : 2026: NYD, MLK, Presidents, Memorial, Juneteenth, Jul 3, Labor, General Election Day (even yrs), Veterans, Thanksgiving + day after, Christmas Eve, Christmas, New Year's Eve. No Columbus.
MN: https://www.revisor.mn.gov/statutes/cite/645.44 : NYD, MLK, Washington's+Lincoln's, Memorial, Juneteenth, Jul 4, Labor, Indigenous Peoples' Day, Veterans, Thanksgiving, Christmas; Friday after Thanksgiving for executive branch.
MS: https://law.justia.com/codes/mississippi/2020/title-3/chapter-3/section-3-3-7/ (Miss. Code 3-3-7 mirror) + https://www.sos.ms.gov/content/documents/ed_pubs/holidays/DOC102025-10202025100420.pdf : legal holidays NYD, MLK/Lee, Washington's, Confederate Memorial Day (last Mon Apr), Memorial/Jefferson Davis, Jul 4, Labor, Veterans, Thanksgiving, Christmas; Sunday->Monday only. Governor's 2025-26 closure order covers only Thanksgiving, Christmas, NYD; ITS says several legal holidays are not full closures -> MS is uncertain.
MO: https://oa.mo.gov/print/468 : 2026: NYD, MLK, Lincoln's Birthday, Washington's, Truman Day (May 8), Memorial, Juneteenth, Jul 4 (Jul 3), Labor, Columbus, Veterans, Thanksgiving, Christmas (2027 shows Sat->Fri). Nov 27 2026 is by Executive Order only (excluded).
MT: https://mca.legmt.gov/bills/mca/title_0010/chapter_0010/part_0020/section_0160/0010-0010-0020-0160.html : MCA 1-1-216: NYD, MLK, Presidents', Memorial, Jul 4, Labor, Indigenous Peoples' Day and Columbus Day, Veterans, Thanksgiving, Christmas, general election day (even yrs); Sat->Fri, Sun->Mon. No Juneteenth.
NE: https://nebraskalegislature.gov/laws/statutes.php?statute=25-2221 : NYD, MLK, President's Day, Arbor Day (last Fri Apr), Memorial, Juneteenth, Jul 4, Labor, Indigenous Peoples' Day/Columbus Day, Veterans, Thanksgiving, day after, Christmas; Sat->Fri, Sun->Mon (84-1001).
NV: https://www.leg.state.nv.us/NRS/NRS-236.html : NRS 236.015: NYD, MLK, Washington's, Memorial, Juneteenth, Jul 4, Labor, Nevada Day (last Fri Oct), Veterans, Thanksgiving, Family Day (Fri after), Christmas; Sat->Fri/Sun->Mon for the five fixed-date days.
NH: https://gencourt.state.nh.us/rsa/html/XXV/288/288-1.htm + https://apps.das.nh.gov/EmployeePortal/comp-savings/state-holidays/ : DAS state list: NYD, MLK/Civil Rights Day, President's Day, Memorial, Jul 4, Labor, Veterans, Thanksgiving, Day after Thanksgiving, Christmas. RSA 288:1 adds Columbus Day and election day but state DAS list omits Columbus; Juneteenth absent from both texts.
NJ: https://law.justia.com/codes/new-jersey/title-11a/section-11a-6-24-1/ (N.J.S.A. 11A:6-24.1 mirror) : NYD, MLK, Washington's/Presidents', Good Friday, Memorial, Juneteenth (third Friday in June), Jul 4, Labor, Columbus, Veterans, Thanksgiving, Christmas, general election day. Encoded Juneteenth as separate nth-Friday rule.
NM: https://www.spo.state.nm.us/wp-content/uploads/2026-Paid-Holiday-Schedule-1.pdf : 2026: NYD, MLK, Memorial, Juneteenth, Jul 3, Labor, Indigenous People's Day, Veterans, Thanksgiving, "Presidents' Day (Observed)" Nov 27, Christmas. Modeled as Day after Thanksgiving; no February holiday, no Columbus.
NY: https://www.cs.ny.gov/attendance_leave/2026_legal_holidays.cfm + https://nysenate.gov/legislation/laws/GCN/24 : GCL 24: NYD, MLK, Lincoln's Birthday (floating for some units), Washington's, Memorial, Juneteenth, Jul 4, Labor, Columbus, Veterans, Thanksgiving, Christmas, general election day. Sunday->Monday; Saturday = pass day, no shift.
NC: https://oshr.nc.gov/state-employee-resources/benefits/leave/holidays : 2026: NYD, MLK, Good Friday, Memorial, Jul 3, Labor, Veterans, Thanksgiving + Friday, Christmas (Dec 24, 25, 28). No Presidents', Columbus, Juneteenth.
ND: https://ndlegis.gov/cencode/t01c03.pdf : NDCC 1-03-01: NYD, MLK, Washington's Birthday, Good Friday, Memorial, Jul 4, Labor, Veterans, Thanksgiving, Christmas; Sat->Fri, Sun->Mon (1-03-02/02.1).
OH: https://www.lsc.ohio.gov/assets/organizations/legislative-service-commission/files/state-holidays.pdf : R.C. 1.14: NYD, MLK, Washington-Lincoln, Memorial, Juneteenth, Jul 4, Labor, Columbus, Veterans, Thanksgiving, Christmas.
OK: https://oklahoma.gov/omes/divisions/human-capital-management/payroll-and-shared-services/leave/state-holidays.html : 2026 (EO 2025-14): NYD, MLK, Presidents', Memorial, Jul 3, Labor, Veterans, Thanksgiving + Friday, Christmas Eve + Christmas. No Columbus/Juneteenth. Friday/Christmas Eve are EO-based -> unconfirmed.
OR: https://www.oregon.gov/das/HR/Documents/2026%20Holiday%20Memo.pdf : NYD, MLK, Presidents', Memorial, Juneteenth, Jul 3, Labor, Veterans, Thanksgiving, Day after Thanksgiving, Christmas.
PA: https://www.pa.gov/content/dam/copapwp-pagov/en/oa/documents/policies/ac/25-13.pdf : 2026: NYD, MLK, Presidents', Memorial, Juneteenth, Jul 3, Labor, Indigenous People's Day, Veterans, Thanksgiving, Day after, Christmas.
RI: https://webserver.rilegislature.gov/Statutes/TITLE25/25-1/25-1-1.HTM : R.I.G.L. 25-1-1: NYD, MLK, Washington's, RI Independence Day (May 4), Memorial, Juneteenth, Jul 4, Victory Day (2nd Mon Aug), Labor, Columbus, Veterans, Christmas, general election day (even yrs), days of thanksgiving (Thanksgiving kept via that clause). Weekend holidays go to following Monday.
SC: https://treasurer.sc.gov/media/5hkfeyd4/2026-state-of-sc-holiday-memo.pdf : 2026: NYD, MLK, President's Day, Confederate Memorial Day (May 11, observed for May 10), Memorial, Juneteenth, Jul 3, Labor, Columbus, Veterans, Thanksgiving, Day after, Christmas Eve, Christmas, Dec 28. Dec 24/28 left out (annual).
SD: https://law.justia.com/codes/south-dakota/2022/title-1/chapter-05/section-1-5-1/ (SDCL 1-5-1 mirror) : NYD, MLK, Presidents' (Lincoln/Washington), Memorial, Juneteenth, Jul 4, Labor, Native Americans' Day (2nd Mon Oct), Veterans, Thanksgiving, Christmas; weekend rule for the five fixed dates.
TN: https://www.tn.gov/hr/employees1/state-holidays.html : 2026: NYD, MLK, President's Day, Good Friday, Memorial, Juneteenth, Jul 3, Labor, Veterans, Thanksgiving + Friday, Christmas Dec 24+25, Dec 31/Jan 1. Columbus footnoted as substituted for the Friday after Thanksgiving (not a separate day).
TX: https://hr.sao.texas.gov/Documents/Holidays/Holidays2026.pdf + https://www.tsl.texas.gov/ref/abouttx/holidays : full closures NYD, MLK, Presidents', Memorial, Jul 4, Labor, Veterans, Thanksgiving, Friday after, Dec 24, 25, 26. Partial staffing only (not days off): Confederate Heroes Day, TX Independence Day, San Jacinto Day, Emancipation Day (Jun 19), LBJ Day; optional: Good Friday. Weekend holidays are not shifted.
UT: https://le.utah.gov/Session/2026/bills/enrolled/SB0193.pdf (63G-1-301 as amended eff. 2026-05-06) : NYD, MLK, Presidents', Memorial, Juneteenth, Jul 4, Pioneer Day Jul 24, Labor, Columbus, Veterans, Thanksgiving, Christmas; Good Friday only 4 hours (excluded).
VT: https://legislature.vermont.gov/statutes/section/01/007/00371 : 1 V.S.A. 371: NYD, MLK, Presidents', Town Meeting Day (1st Tue Mar), Memorial, Juneteenth, Jul 4, Bennington Battle Day (Aug 16), Labor, Indigenous Peoples' Day, Veterans, Thanksgiving, Christmas; Sat->Fri, Sun->Mon.
VA: https://law.lis.virginia.gov/vacode/title2.2/chapter33/section2.2-3300/ : NYD, MLK, George Washington Day, Memorial, Juneteenth, Jul 4, Labor, Columbus/Yorktown Victory Day, Election Day (every year), Veterans, Thanksgiving + Friday, Christmas; Sat->Fri, Sun->Mon. Lee-Jackson Day not in statute.
WA: https://lawfilesext.leg.wa.gov/law/RCW/RCW%20%20%201%20%20TITLE/RCW%20%20%201%20.%2016%20%20CHAPTER/RCW%20%20%201%20.%2016%20.050.htm : RCW 1.16.050: NYD, MLK, Presidents', Memorial, Juneteenth, Jul 4, Labor, Veterans, Thanksgiving, Native American Heritage Day (Fri after), Christmas; Sat->Fri, Sun->Mon. No Columbus.
WV: https://code.wvlegislature.gov/2-2-1/ : NYD, MLK, Presidents', Memorial, West Virginia Day (Jun 20), Jul 4, Labor, Columbus, Veterans, Thanksgiving, Lincoln's Day (Fri after), Christmas, election days; Sat->Fri, Sun->Mon. No Juneteenth (SB793 of 2026 did not add it).
WI: https://docs.legis.wisconsin.gov/statutes/statutes/230/ii/35/4 : Wis. Stat. 230.35(4)(a): Jan 1, MLK (3rd Mon Jan), Memorial, Jul 4, Labor, Thanksgiving, Dec 24, Dec 25, Dec 31; Sunday->Monday for Jan 1, Jul 4, Dec 25 only. No Presidents', Veterans, Columbus, Juneteenth.
WY: https://law.justia.com/codes/wyoming/title-8/chapter-4/section-8-4-101 (W.S. 8-4-101 mirror) : NYD, MLK/Wyoming Equality Day, Washington's+Lincoln's, Memorial, Jul 4, Labor, Veterans, Thanksgiving, Christmas; Sunday->Monday for NYD, Jul 4, Christmas, Veterans. No Juneteenth/Columbus.

## Federal observed-date rule
5 U.S.C. 6103(b): holiday on Saturday -> preceding Friday; Sunday -> following Monday (for employees on Mon-Fri schedules). Not fetched this session (no OPM page retrieved); confirmed indirectly by state pages showing Jul 3 2026 (e.g. AK, CO, GA, KS, PA).

## Observed-rule deviations (per-state; encoded by splitting fixed-date holidays into sat-fri-sun-mon / sun-mon / none ids)
- sun-mon (Saturday not shifted): CA (except Veterans Day Sat -> Fri, not encoded), MA, NY (Saturday = pass day credit), WI, WY, MS.
- none: TX (weekend holidays not shifted).
- Not representable: RI (both Saturday and Sunday holidays -> following Monday; encoded as sun-mon). CA Veterans exception. AZ Sat->Fri only for NYD/Jul 4/Veterans/Christmas (equivalent for the days encoded). DC/others standard. NH, NJ, IL, HI, NC: no explicit weekend rule found; standard assumed (NC Dec 28 for Christmas Saturday observed matches sat-fri-sun-mon only partly; NC's Christmas multi-day not modeled).

## Unconfirmed / left out
- Even-year-only election days (format cannot express): DE, HI, IL, IN (also Primary), LA, MD, MI, MT, NH, RI, WI, WV(+primary); also Inauguration Day (DC, LA).
- Governor/EO-set or one-year-only: day after Thanksgiving in GA, IN, KS, KY, MO, MS, OK; Christmas Eve/Dec 26-28/NYE in KS, KY, NC, OK, SC, TN; GA floating April/December days; Jan 2 closures (MS, TN, LA); IN Dec 24 "Washington's Birthday"; IN Lincoln's Birthday (Nov 27) recurrence.
- Partial/optional: TX Confederate Heroes, TX Independence, San Jacinto, Emancipation, LBJ; Good Friday in KY (half), UT (4 hrs), TX (optional), CA (3 hr), GA.
- County/local: AL Mardi Gras (Mobile/Baldwin), MA Evacuation/Bunker Hill (Suffolk), LA Columbus (Sulphur), Cesar Chavez Day in CO/TX not state holidays.
- Not on state-employee schedule although statute lists: CA (Juneteenth, Lincoln, Columbus, Admission Day, Native American Day, Lunar New Year, Diwali, Genocide Remembrance), IA Lincoln/Washington (1C.1).
- IL Pulaski Day / Malcolm X Day, FL Susan B. Anthony Day, NY/WI/WV Susan B. Anthony Day, PA Flag Day, SC Boxing Day, MT Christmas Eve/NYE: not in the sources checked.
- Not fetched / weaker evidence: CO statute, IL (2022/23 memo), NH 2026 dates, KS/NM/CA/NC/OR/PA/TN single-schedule evidence for recurring rules, LA Memorial Day (included though only governor-proclaimed by statute), MS (see above), Juneteenth in KY (2026 page only), SC annual-budget days (Columbus, Juneteenth, day after Thanksgiving).
- Web-fetch budget (100/hour) was exhausted partway; several states were verified with a single page.

## US-disagreements

### Disagreements with oracle (date-holidays 3.39.2, types public/bank, 2026-2028)
Source wins in every case below. Dates compared include observed dates. "oracle-only" = oracle lists it, dataset omits (usually because the state schedule/statute does not give a day off); "ours-only" = dataset has it, oracle does not.

## Systematic
- Juneteenth: oracle assumes all states; dataset excludes AZ AR CA FL HI ID IN IA LA MS MT NH NC ND OK TX WV WI WY (not in the state statute / state-employee schedule; see US-sources.md). NJ keeps it on the third Friday of June (N.J.S.A. 11A:6-24.1), which differs from June 19 in 2028.
- Columbus Day: oracle lists nearly all states; dataset omits it where the state schedule/statute shows no day off (AK AR CA CO DE DC FL HI IA KS KY LA ME MI MN MS NH NM NC ND OK OR PA SD TN TX VT WA WI WY).
- Observed dates: oracle adds (sub) days for Saturday holidays in MA, NY, CA, MS, RI, TX, WY, ... where the state rule is Sunday->Monday only; dataset follows the state rule (sun-mon / none).
- Election Day (even years only: DE HI IL IN LA MD MI MT NH WI WV...): oracle lists; dataset leaves out (spec cannot express even-year-only). Exception VA, NY, NJ (every year per statute) are included; VA 2027-11-02 is ours-only because the oracle only has even years.
- Susan B. Anthony Day (FL NY WV WI): oracle only; not in the state sources checked (WV statute names even-year general election day "Susan B. Anthony Day", not Feb 15).
- Day after Thanksgiving: ours-only for IL IA ME MI MN NE OR PA SC TN VA WA (state sources list it); oracle-only for OK (EO-based, unconfirmed), IN/MD naming differs.
- Christmas Eve / New Year's Eve: oracle lists KY MT NC TN (and others); dataset only AR MI TX (Dec 24), WI MI (Dec 31), TX Dec 26. KY/NC/TN set annually; MT statute has none.
- Names: oracle Lee-King, "Confederate Memorial Day" etc. appear in dataset only where statute lists them (AL, MS, SC).

## Specific
- GA: oracle 04-27 "State Holiday" and Dec 24 "Washington's Birthday": dataset leaves out (annually set); no Feb 16 Presidents' Day for GA (also DE FL IN IA KS KY LA NM NC WI).
- IN: oracle has Presidents' Day Feb 16; Indiana's state schedule has none (Dec 24 instead). Oracle Juneteenth also absent from IN memo.
- IA: oracle Lincoln/Washington/Indigenous Peoples' Day not paid holidays under Iowa Code 1C.2.
- MA: oracle Evacuation Day, Bunker Hill Day: Suffolk County only; excluded.
- IL: oracle Casimir Pulaski Day and Malcolm X Day: not on the Illinois state-holiday memo; unconfirmed.
- KY: oracle Good Friday: only a half day under KRS 18A.190/state page.
- TX: oracle Juneteenth/Columbus: Emancipation Day is partial staffing; Columbus not a state holiday.
- NM: oracle Presidents' Day Feb 16: NM observes it on the Friday after Thanksgiving (modeled as Day after Thanksgiving).
- ND: ours-only Good Friday (NDCC 1-03-01 lists it; oracle omits).
- WI: oracle lists Veterans, Columbus, Presidents', Juneteenth; Wis. Stat. 230.35(4)(a) lists none of them but includes Dec 24/Dec 31 (ours-only).
- VT: Bennington Battle Day: statute Aug 16 (oracle uses another date in 2028, 08-21).
- RI: ours-only Rhode Island Independence Day (May 4, statute).
- SC: oracle Boxing Day Dec 26; SC 2026 memo lists Dec 28; left out as annual.
- CA: oracle Juneteenth; CalHR schedule has none.

## Raw date-level diff (2026-2028)
## AL Alabama

## AK Alaska
- oracle-only 2026-10-12: Indigenous Peoples’ Day
- oracle-only 2027-10-11: Indigenous Peoples’ Day
- oracle-only 2028-10-09: Indigenous Peoples’ Day
- ours-only 2026-10-19: Alaska Day (obs)

## AZ Arizona
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2028-06-19: Juneteenth

## AR Arkansas
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-10-09: Columbus Day

## CA California
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-07-03: Independence Day (substitute day) (sub)
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-12-24: Christmas Day (substitute day) (sub)
- oracle-only 2027-12-31: New Year’s Day (substitute day) (sub)
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-11-10: Veterans Day (substitute day) (sub)

## CO Colorado

## CT Connecticut
- ours-only 2028-02-11: Lincoln's Birthday (obs)

## DE Delaware
- oracle-only 2026-11-03: Election Day
- oracle-only 2028-11-07: Election Day

## DC District of Columbia
- ours-only 2028-04-17: DC Emancipation Day (obs)

## FL Florida
- oracle-only 2026-02-15: Susan B. Anthony Day
- oracle-only 2026-02-16: Commemoration of America’s 250th Birthday
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2027-02-15: Susan B. Anthony Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2028-02-15: Susan B. Anthony Day
- oracle-only 2028-06-19: Juneteenth

## GA Georgia
- oracle-only 2026-04-27: State Holiday
- oracle-only 2026-11-27: State Holiday
- oracle-only 2026-12-24: Washington’s Birthday
- oracle-only 2027-04-26: State Holiday
- oracle-only 2027-11-26: State Holiday
- oracle-only 2028-04-24: State Holiday
- oracle-only 2028-11-24: State Holiday
- oracle-only 2028-12-24: Washington’s Birthday

## HI Hawaii
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-11-03: General Election Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-11-07: General Election Day

## ID Idaho
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2028-06-19: Juneteenth

## IL Illinois
- oracle-only 2026-03-02: Casimir Pulaski Day
- oracle-only 2026-05-19: Malcolm X Day
- oracle-only 2026-11-03: Election Day
- oracle-only 2027-03-01: Casimir Pulaski Day
- oracle-only 2027-05-19: Malcolm X Day
- oracle-only 2028-03-06: Casimir Pulaski Day
- oracle-only 2028-05-19: Malcolm X Day
- oracle-only 2028-11-07: Election Day
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-02-11: Lincoln's Birthday (obs)
- ours-only 2028-11-24: Day after Thanksgiving

## IN Indiana
- oracle-only 2026-02-16: Washington’s Birthday
- oracle-only 2026-05-05: Primary Election Day
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-11-03: Election Day
- oracle-only 2026-11-27: Lincoln’s Birthday
- oracle-only 2027-02-15: Washington’s Birthday
- oracle-only 2027-05-04: Primary Election Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-11-02: Election Day
- oracle-only 2027-11-26: Lincoln’s Birthday
- oracle-only 2028-02-21: Washington’s Birthday
- oracle-only 2028-05-02: Primary Election Day
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-11-07: Election Day
- oracle-only 2028-11-24: Lincoln’s Birthday

## IA Iowa
- oracle-only 2026-02-12: Lincoln’s Birthday
- oracle-only 2026-02-16: Washington’s Birthday
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-10-12: Indigenous Peoples’ Day
- oracle-only 2027-02-12: Lincoln’s Birthday
- oracle-only 2027-02-15: Washington’s Birthday
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-10-11: Indigenous Peoples’ Day
- oracle-only 2028-02-12: Lincoln’s Birthday
- oracle-only 2028-02-21: Washington’s Birthday
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-10-09: Indigenous Peoples’ Day
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-11-24: Day after Thanksgiving

## KS Kansas
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2028-10-09: Columbus Day

## KY Kentucky
- oracle-only 2026-04-03: Good Friday
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2026-12-24: Christmas Eve
- oracle-only 2026-12-31: New Year’s Eve
- oracle-only 2027-03-26: Good Friday
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2028-04-14: Good Friday
- oracle-only 2028-10-09: Columbus Day
- oracle-only 2028-12-24: Christmas Eve
- oracle-only 2028-12-31: New Year’s Eve

## LA Louisiana
- oracle-only 2026-02-16: Washington’s Birthday
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2026-11-03: Election Day
- oracle-only 2027-02-15: Washington’s Birthday
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2028-02-21: Washington’s Birthday
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-10-09: Columbus Day
- oracle-only 2028-11-07: Election Day

## ME Maine
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-11-24: Day after Thanksgiving

## MD Maryland

## MA Massachusetts
- oracle-only 2026-03-17: Evacuation Day
- oracle-only 2026-06-17: Bunker Hill Day
- oracle-only 2026-07-03: Independence Day (substitute day) (sub)
- oracle-only 2027-03-17: Evacuation Day
- oracle-only 2027-06-17: Bunker Hill Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-12-24: Christmas Day (substitute day) (sub)
- oracle-only 2027-12-31: New Year’s Day (substitute day) (sub)
- oracle-only 2028-03-17: Evacuation Day
- oracle-only 2028-06-17: Bunker Hill Day
- oracle-only 2028-11-10: Veterans Day (substitute day) (sub)

## MI Michigan
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2028-10-09: Columbus Day
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-11-24: Day after Thanksgiving

## MN Minnesota
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-11-24: Day after Thanksgiving

## MS Mississippi
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-07-03: Independence Day (substitute day) (sub)
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2027-12-24: Christmas Day (substitute day) (sub)
- oracle-only 2027-12-31: New Year’s Day (substitute day) (sub)
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-10-09: Columbus Day
- oracle-only 2028-11-10: Veterans Day (substitute day) (sub)

## MO Missouri
- ours-only 2027-05-07: Truman Day (obs)
- ours-only 2028-02-11: Lincoln's Birthday (obs)

## MT Montana
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-11-03: Election Day
- oracle-only 2026-12-24: Christmas Eve
- oracle-only 2026-12-31: New Year’s Eve
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-11-02: Election Day
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-11-07: Election Day
- oracle-only 2028-12-24: Christmas Eve
- oracle-only 2028-12-31: New Year’s Eve

## NE Nebraska
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-11-24: Day after Thanksgiving

## NV Nevada

## NH New Hampshire
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-11-03: Election Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-11-07: Election Day

## NJ New Jersey
- oracle-only 2026-02-12: Lincoln’s Birthday
- oracle-only 2027-02-12: Lincoln’s Birthday
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2028-02-12: Lincoln’s Birthday
- oracle-only 2028-06-19: Juneteenth
- ours-only 2028-06-16: Juneteenth

## NM New Mexico
- oracle-only 2026-02-16: Presidents’ Day
- oracle-only 2027-02-15: Presidents’ Day
- oracle-only 2028-02-21: Presidents’ Day

## NY New York
- oracle-only 2026-02-15: Susan B. Anthony Day
- oracle-only 2026-07-03: Independence Day (substitute day) (sub)
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-12-24: Christmas Day (substitute day) (sub)
- oracle-only 2027-12-31: New Year’s Day (substitute day) (sub)
- oracle-only 2028-02-15: Susan B. Anthony Day
- oracle-only 2028-11-10: Veterans Day (substitute day) (sub)

## NC North Carolina
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-12-24: Christmas Eve
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-12-23: Christmas Eve (substitute day) (sub)
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-12-22: Christmas Eve (substitute day) (sub)
- oracle-only 2028-12-24: Christmas Eve

## ND North Dakota
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-10-09: Columbus Day
- ours-only 2026-04-03: Good Friday
- ours-only 2027-03-26: Good Friday
- ours-only 2028-04-14: Good Friday

## OH Ohio

## OK Oklahoma
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2026-11-27: Day after Thanksgiving
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2027-11-26: Day after Thanksgiving
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-10-09: Columbus Day
- oracle-only 2028-11-24: Day after Thanksgiving

## OR Oregon
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2028-10-09: Columbus Day
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-11-24: Day after Thanksgiving

## PA Pennsylvania
- oracle-only 2026-06-14: Flag Day
- oracle-only 2027-06-14: Flag Day
- oracle-only 2028-06-14: Flag Day
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-11-24: Day after Thanksgiving

## RI Rhode Island
- oracle-only 2026-07-03: Independence Day (substitute day) (sub)
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-12-24: Christmas Day (substitute day) (sub)
- oracle-only 2027-12-31: New Year’s Day (substitute day) (sub)
- oracle-only 2028-11-10: Veterans Day (substitute day) (sub)
- ours-only 2026-05-04: Rhode Island Independence Day
- ours-only 2027-05-04: Rhode Island Independence Day
- ours-only 2028-05-04: Rhode Island Independence Day

## SC South Carolina
- oracle-only 2026-12-26: Boxing Day
- oracle-only 2027-12-26: Boxing Day
- oracle-only 2028-12-26: Boxing Day
- ours-only 2026-05-11: Confederate Memorial Day (obs)
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-11-24: Day after Thanksgiving

## SD South Dakota

## TN Tennessee
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2026-12-24: Christmas Eve
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2028-10-09: Columbus Day
- oracle-only 2028-12-24: Christmas Eve
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-11-24: Day after Thanksgiving

## TX Texas
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-07-03: Independence Day (substitute day) (sub)
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-07-05: Independence Day (substitute day) (sub)
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2027-12-31: New Year’s Day (substitute day) (sub)
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-10-09: Columbus Day
- oracle-only 2028-11-10: Veterans Day (substitute day) (sub)

## UT Utah
- ours-only 2027-07-23: Pioneer Day (obs)

## VT Vermont
- oracle-only 2028-08-21: Bennington Battle Day
- ours-only 2026-08-16: Bennington Battle Day
- ours-only 2028-08-16: Bennington Battle Day

## VA Virginia
- ours-only 2026-11-27: Day after Thanksgiving
- ours-only 2027-11-02: Election Day
- ours-only 2027-11-26: Day after Thanksgiving
- ours-only 2028-11-24: Day after Thanksgiving

## WA Washington
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2028-10-09: Columbus Day
- ours-only 2026-11-27: Native American Heritage Day
- ours-only 2027-11-26: Native American Heritage Day
- ours-only 2028-11-24: Native American Heritage Day

## WV West Virginia
- oracle-only 2026-02-15: Susan B. Anthony Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2028-02-15: Susan B. Anthony Day
- oracle-only 2028-06-19: Juneteenth
- ours-only 2027-06-21: West Virginia Day (obs)

## WI Wisconsin
- oracle-only 2026-02-15: Susan B. Anthony Day
- oracle-only 2026-02-16: Washington’s Birthday
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-07-03: Independence Day (substitute day) (sub)
- oracle-only 2026-09-08: Primary Election Day
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2026-11-03: Election Day
- oracle-only 2026-11-11: Veterans Day
- oracle-only 2027-02-15: Susan B. Anthony Day; Washington’s Birthday
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-09-14: Primary Election Day
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2027-11-02: Election Day
- oracle-only 2027-11-11: Veterans Day
- oracle-only 2028-02-15: Susan B. Anthony Day
- oracle-only 2028-02-21: Washington’s Birthday
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-09-12: Primary Election Day
- oracle-only 2028-10-09: Columbus Day
- oracle-only 2028-11-07: Election Day
- oracle-only 2028-11-10: Veterans Day (substitute day) (sub)
- oracle-only 2028-11-11: Veterans Day
- ours-only 2026-12-24: Christmas Eve
- ours-only 2026-12-31: New Year's Eve
- ours-only 2028-12-24: Christmas Eve
- ours-only 2028-12-31: New Year's Eve

## WY Wyoming
- oracle-only 2026-06-19: Juneteenth
- oracle-only 2026-07-03: Independence Day (substitute day) (sub)
- oracle-only 2026-10-12: Columbus Day
- oracle-only 2027-06-18: Juneteenth (substitute day) (sub)
- oracle-only 2027-06-19: Juneteenth
- oracle-only 2027-10-11: Columbus Day
- oracle-only 2027-12-24: Christmas Day (substitute day) (sub)
- oracle-only 2027-12-31: New Year’s Day (substitute day) (sub)
- oracle-only 2028-06-19: Juneteenth
- oracle-only 2028-10-09: Columbus Day
- oracle-only 2028-11-10: Veterans Day (substitute day) (sub)

## CA-sources

### CA sources (fetched 2026-11-27). Budget of web fetches ran out near the end; items marked UNVERIFIED rest on background knowledge only.

## Federal (reference only, not in JSON)
- https://www.canada.ca/en/services/jobs/workplace/federal-labour-standards/vacations-holidays.html : 10 general holidays (see CA-federal-notes.md); weekend rule.

## AB  https://www.alberta.ca/alberta-general-holidays
Confirmed 9: New Year's, Family Day (3rd Mon Feb), Good Friday, Victoria Day, Canada Day, Labour Day, Thanksgiving, Remembrance Day, Christmas. Easter Monday, Boxing Day, Sept 30 are optional (not general holidays).
Weekend: only rule on page: Canada Day "July 1, except when it falls on a Sunday, then it is July 2" (used: sun-mon). Otherwise holiday on a non-regular day: no pay, no day in lieu. Family Day start 1990: UNVERIFIED.

## BC  https://www2.gov.bc.ca/gov/content/employment-business/employment-standards-advice/employment-standards/statutory-holidays
Confirmed 11: NYD, Family Day, Good Friday, Victoria, Canada Day, B.C. Day, Labour, Truth and Reconciliation (listed 2023-2027), Thanksgiving, Remembrance, Christmas. Easter Monday/Boxing Day not statutory.
Family Day: https://news.gov.bc.ca/releases/2018PREM0018-000188 : first set 2013 on 2nd Monday, 3rd week of Feb from 2019 (modelled as two entries).
Weekend: page states no rule; employer/employee may agree in writing to substitute (ESA ss.44-46, not read). B.C. Day = first Monday Aug: matches 2026 date (Aug 3) on page; rule itself UNVERIFIED beyond that.

## MB  https://www.gov.mb.ca/labour/standards/doc,gen-holidays-after-april-30-07,factsheet.html
Confirmed 9: NYD, Louis Riel Day (3rd Mon Feb), Good Friday, Victoria, July 1, Labour, Orange Shirt Day, Thanksgiving, Christmas. Remembrance Day, Boxing Day, Terry Fox Day not general holidays.
Orange Shirt: https://web2.gov.mb.ca/laws/statutes/2023/pdf/c05023.pdf : in force on assent 7 Dec 2023, so first Sept 30 is 2024 (my deduction). Louis Riel since 2008: UNVERIFIED.
Weekend: Sat/Sun non-working -> next regular workday off with pay (not a fixed calendar shift; employee-specific). Orange Shirt cannot be substituted.

## NB  https://laws.gnb.ca/en/document/cs/E-7.2 (text current to 1 Jan 2024)
"public holiday": NYD, Family Day, Good Friday, Canada Day, New Brunswick Day, Labour Day, Remembrance Day, Christmas Day. Not listed: Victoria, Thanksgiving, Boxing, Easter Mon, Sept 30. Family Day since 2018: https://laws.gnb.ca/en/document/as/2017,%20c.38 (search hit, content not fetched) -- 2018 corroborated only by news search results.
Weekend (s.18(4)): non-working day -> employer pays regular wages (with agreement) or designates a working day no later than next vacation. No fixed shift. New Brunswick Day = 1st Mon Aug: UNVERIFIED rule. Gov-employee page (not used for law): https://www.gnb.ca/en/topic/your-gov/work/policies-guidelines/statutory-public-holidays.html consistent.

## NL  https://assembly.nl.ca/legislation/sr/statutes/l02.htm (Labour Standards Act s.14-16) and https://assembly.nl.ca/Legislation/sr/Regulations/rc960781.htm
s.14(1) public holidays: New Year's Day, Good Friday, Remembrance Day, Memorial Day, Labour Day, Christmas Day (+ LGIC-proclaimed days). Memorial Day = July 1 (in place of Canada Day). Regulation s.4 says dates are as proclaimed under Shops Closing Act (not read).
Weekend (s.16): holiday on a non-required day -> employer cannot require work on the 1st working day after it (or agreed day); s.15(2) pay applies. No fixed shift.
NOT statutory: St Patrick's, St George's, Discovery/June Holiday, Orangemen's, Victoria Day, Thanksgiving, Boxing Day, Sept 30. https://www.gov.nl.ca/exec/tbs/2026-paid-holidays lists them (plus Victoria, Thanksgiving, Boxing Day, Sept 30, civic holiday) only for Government of NL employees -> left out.

## NS  https://novascotia.ca/lae/employmentrights/holidaypay.asp
Six paid holidays: NYD, Nova Scotia Heritage Day, Good Friday, Canada Day, Labour Day, Christmas Day. Remembrance Day is under the Remembrance Day Act (business closure + pay-in-lieu), "not a general paid holiday under the Labour Standards Code" -> excluded.
Heritage Day: https://nslegislature.ca/sites/default/files/legc/statutes%20HTML/february%20holiday.htm : 3rd Mon Feb from 2015. Weekend: different day off (next scheduled day / added to vacation / agreed). No fixed shift.

## NT  https://www.justice.gov.nt.ca/en/files/legislation/employment-standards/employment-standards.a.pdf (s.22) and https://www.ece.gov.nt.ca/en/content/employment-standards-frequently-asked-questions
Confirmed 11: NYD, Good Friday, Victoria, National Indigenous Peoples Day (Jun 21), Canada Day, First Monday in August, Labour, Truth and Reconciliation, Thanksgiving, Remembrance, Christmas. Easter Monday and Boxing Day are NOT statutory.
TRC since 2022: https://www.gov.nt.ca/en/newsroom/national-day-truth-and-reconciliation-declared-statutory-holiday-northwest-territories (Bill 47, in force 3 Jun 2022).
Weekend: https://www.ece.gov.nt.ca/en/content/employment-standards-fact-sheet : non-working day -> average day's pay or another day off; "if a holiday falls on a Sunday, the employer can transfer the holiday to Monday" (employer option, not fixed -> 'none'). s.22(2)-(3): substitution by collective agreement/officer approval.

## NU  https://www.gov.nu.ca/en/employment-training-and-career-development/labour-and-employment-standards-nunavut
Confirmed 11: NYD, Good Friday, Victoria, Canada Day, Nunavut Day, August Civic Holiday (1st Mon Aug), Labour, TRC, Thanksgiving, Remembrance, Christmas. NO National Indigenous Peoples Day, no Boxing/Easter Monday.
Nunavut Day Jul 9 beginning 2020: https://gov.nu.ca/justice/news/nunavut-day-designated-general-holiday-start-date and https://www.gov.nu.ca/sites/default/files/media/imp/2020-06_psa_jus_nunavut_day_general_holiday_-_eng.pdf.
TRC: Bill 5 (https://www.assembly.nu.ca/sites/default/files/Bill%205_re.%20National%20Day%20for%20Truth%20and%20Reconciliation_EF.pdf) adds it to Labour Standards Act; assent date not shown; Nunatsiaq says hoped statutory by 2022. since 2022 is UNVERIFIED (current list confirms it now).
Weekend rule: could not read the Act (nunavutlegislation.ca blocked) -> UNKNOWN.

## ON  https://www.ontario.ca/document/your-guide-employment-standards-act-0/public-holidays
9 holidays: NYD, Family Day, Good Friday, Victoria, Canada Day, Labour, Thanksgiving, Christmas, Boxing Day (Dec 26 IS a public holiday). Civic Holiday, Easter Monday, Remembrance Day are NOT. Family Day since 2008: UNVERIFIED.
Weekend/non-working day: employee gets substitute holiday with pay (to be taken within 3 months, or 12 months by agreement) or just public-holiday pay if agreed in writing. Page does not say who picks the date. No fixed shift.

## PE  https://www.princeedwardisland.ca/en/information/workforce-and-advanced-learning/paid-holidays
8: NYD, Islander Day (3rd Mon Feb), Good Friday, Canada Day, Labour, TRC, Remembrance, Christmas. (No Victoria, Thanksgiving, Boxing.) Weekend: employer gives paid day off on another date (next scheduled day / first workday after vacation / agreed before next vacation). Note: Act replaced June 30, 2026 (https://www.princeedwardisland.ca/en/information/workforce-and-advanced-learning/changes-to-the-employment-standards-act); not checked whether list changed.
TRC: gov observed from 2021 (https://www.princeedwardisland.ca/en/news/province-recognizes-national-day-of-truth-and-reconciliation); statutory amendment planned fall 2021 -> since 2022 is UNVERIFIED. Islander Day since 2009 UNVERIFIED.

## QC  https://cnesst.gouv.qc.ca/en/working-conditions/leave/statutory-holidays/statutory-holidays (also https://educaloi.qc.ca/capsules/les-conges-feries/ secondary)
8: Jan 1; Good Friday OR Easter Monday (employer's choice); Monday before May 25 (National Patriots' Day); June 24; July 1; Labour Day; Thanksgiving; Dec 25. No Family Day, no Sept 30, no Remembrance Day.
Weekend: Sunday June 24 -> June 25 (not for those who normally work Sundays); Sunday July 1 -> July 2; no rule for Saturday or other holidays. Modelled as sun-mon for these two holidays.
Good Friday/Easter Monday: left OUT (see unconfirmed).

## SK  https://saskatchewan.ca/business/employment-standards/public-statutory-holidays/list-of-saskatchewan-public-holidays and .../paying-employees-for-public-holidays
10: NYD, Family Day, Good Friday, Victoria, Canada Day, Saskatchewan Day, Labour, Thanksgiving, Remembrance, Christmas. Sept 30, Easter Monday, Boxing Day NOT. Weekend: only that some employers offer 5% pay or an extra paid day for a day off falling on holiday; no fixed shift. Family Day since 2007: UNVERIFIED. Saskatchewan Day = 1st Mon Aug (2024: Aug 5 on page).

## YT  https://yukon.ca/en/find-yukon-statutory-holiday
11: NYD, Good Friday, Victoria, National Indigenous Peoples Day, Canada Day, Discovery Day (Aug 17 in 2026), Labour, TRC, Thanksgiving, Remembrance, Christmas.
Weekend: day off not worked -> "holiday on their next working day immediately following the holiday" (employee-specific, not fixed -> 'none').
TRC since 2023: https://yukon.ca/en/news/national-day-truth-and-reconciliation-track-become-statutory-day-yukon (planned 2023; UNVERIFIED enactment). Discovery Day rule (3rd Monday Aug) inferred from the 2026 date and matches oracle: UNVERIFIED in law text.

## Unconfirmed / left out
- QC Good Friday vs Easter Monday: law lets each employer choose; no single day -> omitted.
- NL St Patrick's, St George's, Discovery/June Holiday, Orangemen's: government-employee only; not in Labour Standards Act.
- Nunavut weekend rule; NT/NU/PE/YT TRC "since" years; Family Day / Louis Riel / Islander Day start years except BC, NS, NB(partial).
- Ontario: whether an Ontario regulation fixes a Christmas/Boxing Day substitution: not checked.

## CA-disagreements

### Oracle (date-holidays 3.39.2) vs authoritative sources, 2026-2028 (generated by cmp.mjs; trusting sources)
- BC, MB, NU, NT, PE, YT: Sept 30 is statutory in the sources but absent from oracle (all six listed; oracle missing).
- NS: oracle adds Remembrance Day; NS Labour Standards Code says not a general paid holiday. Excluded.
- NT: oracle adds Easter Monday and Boxing Day; NT government says neither is statutory. Excluded.
- ON: oracle adds Easter Monday; ESA page says not a public holiday. Excluded.
- PE: oracle adds Gold Cup Parade Day (Aug, regional/local); not in PEI paid-holiday list. Excluded.
- QC: oracle has Good Friday; law gives employer choice of Good Friday or Easter Monday. Omitted (unresolved).
- Task-brief assumptions corrected: Ontario DOES list Boxing Day; NU has no National Indigenous Peoples Day; NL Victoria/Thanksgiving/Boxing/Sept 30 are not statutory; Remembrance Day is statutory in NB, NL, PE, not MB/NS/ON/QC.
No other differences for AB, NB, NL, SK (exact match).

## CA-federal-notes

### Federal (Canada Labour Code) - reference only
Source: https://www.canada.ca/en/services/jobs/workplace/federal-labour-standards/vacations-holidays.html
General holidays (10): New Year's Day, Good Friday, Victoria Day, Canada Day, Labour Day, National Day for Truth and Reconciliation, Thanksgiving, Remembrance Day, Christmas Day, Boxing Day.
Weekend rule per page: for New Year's, Canada Day, Truth and Reconciliation, Remembrance Day, Christmas, Boxing Day falling on a non-scheduled day: holiday with pay on the scheduled work day immediately before or after. Good Friday, Victoria, Labour, Thanksgiving: paid holiday may be added to annual vacation or given at a mutually convenient time. Employee-specific; not a fixed calendar shift, so not modelled. (Code section number s.166 not seen on the page.)
