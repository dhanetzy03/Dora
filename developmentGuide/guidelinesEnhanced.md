INVENTORY MANAGEMENT SYSTEM W SALES VALIDATION FOR ADMIN

ADMIN

USERS INFORMATION

> COMPANY NAME
> ADMIN NAME
> STAFF NAME

DASHBOARD

> SALES TODAY (tutugma sa collection | date + total sales)
> CRITICAL / OUT OF STOCKS
> COLLECTION FOR THE DAY (PAPASOK NA RESIBO NA BINAYAD | MODE OF PAYMENTS | TOTAL BILL)
> QUICK ACTIONS
 - MANAGE INVENTORY
 - REPORTS
 - ADJUSTING ENTRY

STOCK MONITORING / INENTORY

> TOTAL INVENTORY COST (INVENTORY LIST)
> FAST MOVING (if the stock meets critical level in 2 days | 80 % within the week)
> SLOW MOVING (if 10 days not critical | stock is ordered after 2 months)
> TRANSACTION MONITORING (MOVEMENT)
> ADD INVENTORY LIST (add item button) 
 --TABLE--
 - ITEM CODE
 - ITEM NAME
 - CATEGORY (Perishable. Pre - pack frozen food, Condiments, Beverages, Raw)
 - UNIT (g, kg, mL, L)
 - STOCK
 - COST PER UNIT (PRODUCT COSTS)
 - AMOUNT (COST PER UNIT * QUANTITY
 - RE-ORDER LEVEL (if reached 50 %)
 - ACTIONS (IN -for admin access only)

[ADD INVENTORY LIST BUTTON]

| ITEM CODE | ITEM NAME | CATEGORY | UNIT  | STOCK  | COST PER UNIT  | AMOUNT  | RE-ORDER LEVEL  | ACTIONS  |
|-----------|-----------|----------|-------|--------|----------------|---------|-----------------|----------|
|           |           |          |       |        |                |         |                 |          |


> STOCK MONITORING TRANSACTION (IN | OUT - for spoilage purposes)
  [ITEM CODE] (turns into hyperlink)
 - DATE
 - REF # (resibo)
 - PARTICULARS
 - QUANTITY
 - UNIT COST
 - AMOUNT 
 - IN
 - OUT
 - BALANCE

| [ITEM CODE] | DATE | REF #  | PARTICULARS | QUANTITY | UNIT COST | AMOUNT  | IN | OUT | BALANCE  |
|-------------|------|--------|-------------|----------|-----------|---------|----|-----|----------|
|             |      |        |             |          |           |         |    |     |          |


SALES VALIDATION / TRANSACTION MONITORING (viewing only for the admin side | only the mark up can be edited)

 --TABLE--
 - DATE
 - ORDER NO.
 - QUANTITY
 - UNIT COST
 - MARK UP (can be edited)
 - SELLING PRICE (unit cost * mark up)
 - TOTAL SALES

| DATE | ORDER NO. | QUANTITY | UNIT COST | MARK UP | SELLING PRICE  | TOTAL SALES |
|------|-----------|----------|-----------|---------|----------------|-------------|
|      |           |          |           |         |                |             |

REPORTS 

 --TABLE--
 - DATE
 - ORDER NO.
 - SALES INVOICE
 - BILL
 - MODE OF PAYMENT (CASH, GCASH, CARD)
 - DISCOUNT (SENIOR CITIZEN DISCOUNT, PWD, OTHERS)

[FROM (DATE) TO (DATE) BUTTON]

| DATE | ORDER NO. | SALES INVOICE  | BILL |   MODE OF PAYMENT    |        DISCOUNT        |
|------|-----------|----------------|------| CASH | G CASH | CARD |  SC  |   PWD  | OTHERS |
|      |           |                |      |------| -------| -----|------| -------|--------|

