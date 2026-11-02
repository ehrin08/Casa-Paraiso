"""Build the manuscript diagrams (Figure 1 and Chapter III) as PNGs.

Usage: python build.py [output-dir] [figure-number ...]
Example: python build.py ../../../output/questionnaire/figures/diagrams 04 12a
"""
import math
import sys

from svgkit import Diagram

OUT = sys.argv[1] if len(sys.argv) > 1 else '../../../output/questionnaire/figures/diagrams'
ONLY = sys.argv[2:]


def fig01_ipo():
    d = Diagram(720, 470, size=11)
    cols = [
        ('Input', 10, ['Customer profiles and\nbooking requests', 'Services, add-ons and\noperating hours', 'Therapist schedules and\nQR attendance scans',
                       'Payments, memberships\nand gift vouchers', 'Ratings and reviews', 'RFM customer-group rules']),
        ('Process', 260, ['Role-based sign-in and access', 'Appointment scheduling with\ncapacity checks', 'Payment recording and\ncommission computation (22%)',
                          'Attendance tracking', 'RFM scoring and reward rules', 'Sentiment classification\n(Gemini with rules fallback)', 'Descriptive analytics']),
        ('Output', 510, ['Confirmed appointments and\nschedules (refresh about 15 s)', 'Complimentary add-on\nvouchers', 'Therapist commission and\nattendance records',
                         'Sentiment-labelled\nfeedback summaries', 'Reports and dashboards with\nCSV, Excel and PDF exports']),
    ]
    top, gap, bh = 44, 8, 48
    for title, x, items in cols:
        d.node('g' + title, x, 10, 200, 450, '', kind='group')
        d.text(x + 100, 26, '*' + title.upper(), size=13)
        span = len(items) * bh + (len(items) - 1) * gap
        y = top + (450 - 44 - span) / 2
        for i, label in enumerate(items):
            d.node(f'{title}{i}', x + 12, y + i * (bh + gap), 176, bh, label, kind='proc' if title == 'Process' else 'box', size=11)
    for x in (214, 464):
        d.front.append(f'<polygon points="{x},220 {x + 20},220 {x + 20},206 {x + 42},235 {x + 20},264 {x + 20},250 {x},250" fill="#2f4f3a"/>')
    d.save(OUT, '01-ipo')


def fig04_context():
    d = Diagram(720, 880)
    o, r = (360, 440), 90
    d.node('sys', o[0] - r, o[1] - r, 2 * r, 2 * r, '*0\n*Casa Paraiso\n*Appointment Booking\n*and Spa Management\n*System', kind='circle', size=12.5)
    boxes = {
        'cust': (25, 30, 140, 'Customer'), 'google': (290, 30, 140, 'Google Sign-In'), 'admin': (555, 30, 140, 'Administrator'),
        'recep': (10, 417, 125, 'Receptionist'), 'super': (585, 417, 125, 'Super-\nadministrator'),
        'ther': (25, 804, 140, 'Therapist'), 'gemini': (290, 804, 140, 'Google Gemini API'), 'mail': (555, 804, 140, 'Mail server\n(SMTP)'),
    }
    for k, (x, y, w, label) in boxes.items():
        d.node(k, x, y, w, 46, label, kind='ext')

    def pair(k, to_sys, from_sys, t=0.5, gap=9, off=14):
        c = d.center(k)
        x, y, w, h, _ = d.nodes[k]
        dx, dy = o[0] - c[0], o[1] - c[1]
        n = math.hypot(dx, dy)
        ux, uy = dx / n, dy / n
        nx, ny = -uy, ux
        tb = min((w / 2) / abs(ux) if ux else 1e9, (h / 2) / abs(uy) if uy else 1e9)
        a = (c[0] + ux * tb, c[1] + uy * tb)
        b = (o[0] - ux * (r + 2), o[1] - uy * (r + 2))
        for sign, label, arrow in ((1, to_sys, 'end'), (-1, from_sys, 'start')):
            if not label:
                continue
            pa = (a[0] + nx * gap * sign, a[1] + ny * gap * sign)
            pb = (b[0] + nx * gap * sign, b[1] + ny * gap * sign)
            d.edge([pa, pb], arrow=arrow)
            mx, my = pa[0] + (pb[0] - pa[0]) * t, pa[1] + (pb[1] - pa[1]) * t
            lx, ly = mx + nx * off * sign, my + ny * off * sign
            px = nx * sign
            anchor = 'start' if px > 0.35 else 'end' if px < -0.35 else 'middle'
            if anchor == 'middle':
                ly += (ny * sign) * (label.count('\n')) * 7.5
            d.text(lx, ly, label, size=11.5, anchor=anchor, bg=True)

    pair('cust', 'booking requests,\ncancellations,\nratings and reviews', 'available times,\nconfirmations,\nadd-on vouchers', t=0.3)
    pair('google', 'verified\nidentity', 'sign-in\nrequest', t=0.78)
    pair('admin', 'services, schedules,\noperating hours,\nreward rules, payouts', 'reports and exports,\nsentiment dashboard,\nattendance records', t=0.3)
    pair('recep', 'walk-in bookings,\npayments, outcomes,\nmemberships', 'calendar, alerts,\npayment quotes,\nattendance QR')
    pair('super', 'system error\nreports', 'user accounts\nand roles')
    pair('ther', 'attendance\nQR scans', 'assigned appointments,\nschedule, commissions', t=0.62)
    pair('gemini', 'label and\nconfidence', 'rating and\nredacted comment', t=0.25)
    pair('mail', None, 'verification and\npassword emails', t=0.62)
    d.save(OUT, '04-context-diagram')


def fig05_use_case():
    d = Diagram(720, 1010, size=12)
    d.node('sys', 175, 10, 370, 990, 'Casa Paraiso Appointment Booking and Spa Management System', kind='group', size=11.5)
    cases = [
        ('u1', 'Sign in and manage profile', 'CRTA'),
        ('u2', 'Book an appointment', 'C'),
        ('u3', 'View or cancel own appointments', 'C'),
        ('u4', 'Use a complimentary add-on voucher', 'C'),
        ('u5', 'Rate a completed visit', 'C'),
        ('u6', 'Manage appointments and customers', 'RA'),
        ('u7', 'Record visit outcome and payment', 'RA'),
        ('u8', 'Sell memberships and gift vouchers', 'RA'),
        ('u9', 'Display the attendance QR station', 'RA'),
        ('u10', 'Scan the attendance QR code', 'T'),
        ('u11', 'View schedule and commissions', 'T'),
        ('u12', 'Manage services, add-ons\nand staff schedules', 'A'),
        ('u13', 'Set operating hours and closures', 'A'),
        ('u14', 'Configure RFM reward rules', 'A'),
        ('u15', 'Review feedback and sentiment', 'A'),
        ('u16', 'View reports and export data', 'A'),
        ('u17', 'Pay therapist commissions', 'A'),
        ('u18', 'Manage user accounts and roles', 'S'),
        ('u19', 'Review system errors', 'S'),
    ]
    y0, step = 42, 50.5
    for i, (cid, label, _) in enumerate(cases):
        d.node(cid, 200, y0 + i * step, 320, 38, label, kind='ellipse', size=11.5)
    actors = {'C': (20, 150, 'Customer'), 'R': (20, 390, 'Receptionist'), 'T': (20, 540, 'Therapist'),
              'A': (570, 640, 'Administrator'), 'S': (570, 910, 'Super-\nadministrator')}
    for k, (x, y, label) in actors.items():
        d.node(k, x, y, 130, 82 if '\n' in label else 70, label, kind='actor')
    for cid, _, who in cases:
        for k in who:
            ax, ay, aw, ah, _ = d.nodes[k]
            side = 'l' if k in 'CRT' else 'r'
            start = (ax + aw / 2 + (16 if side == 'l' else -16), ay + 28)
            d.edge([start, d.p(cid, side)], arrow=None, width=1)
    sx, sy, sw, sh, _ = d.nodes['S']
    d.edge([(sx + sw / 2, sy), (sx + sw / 2, d.nodes['A'][1] + d.nodes['A'][3] + 2)], arrow='tri')
    d.text(sx + sw / 2 + 6, sy - 70, 'inherits all\nAdministrator\nuse cases', size=10.5, anchor='start', bg=True)
    d.save(OUT, '05-use-case-diagram')


def fig06_architecture():
    d = Diagram(720, 780, size=12)
    d.node('t1', 15, 10, 520, 150, 'Tier 1 - Presentation: web browser on desktop or mobile', kind='group')
    d.node('b1', 30, 35, 240, 50, '*Blade pages\nTailwind CSS styling', size=11.5)
    d.node('b2', 280, 35, 240, 50, '*Alpine.js and Turbo\ndialogs, calendars, booking wizard', size=11.5)
    d.node('b3', 30, 97, 240, 50, '*Five role workspaces\ncustomer, front desk, therapist, admin', size=11.5)
    d.node('b4', 280, 97, 240, 50, '*Live updates\nchange check about every 15 seconds', size=11.5)

    d.node('t2', 15, 220, 520, 364, 'Tier 2 - Application: Laravel 12 (PHP) on Hostinger web hosting', kind='group')
    d.node('mw', 30, 245, 490, 44, '*Routes and middleware\nsign-in, role access, CSRF tokens, rate limits', size=11.5)
    d.node('ctl', 30, 303, 490, 44, '*Controllers and form requests\nvalidation, then a normal Laravel response', size=11.5)
    d.edge([d.p('mw', 'b'), d.p('ctl', 't')])
    d.edge([d.p('ctl', 'b'), (275, 362)])
    d.text(275, 372, '*Services (business rules)', size=11.5)
    svc = [
        ('s1', '*Booking\noperating hours, customer\nguards, therapist capacity'),
        ('s2', '*Payments\nmemberships, gift vouchers,\n22% default commission'),
        ('s3', '*Attendance\nrotating QR code,\nscan review'),
        ('s4', '*Rewards\nRFM scores, group rules,\nadd-on vouchers'),
        ('s5', '*Reports and exports\ndashboards; CSV, Excel,\nPDF, Print'),
        ('s6', '*Sentiment\nrules classifier + Gemini,\nrules fallback'),
    ]
    for i, (sid, label) in enumerate(svc):
        d.node(sid, 30 + (i % 3) * 165, 386 + (i // 3) * 94, 155, 82, label, size=11.5)

    d.node('t3', 15, 640, 520, 130, 'Tier 3 - Data', kind='group')
    d.node('db', 45, 665, 270, 92, '*MariaDB database\naccounts, appointments, payments,\ncommissions, attendance,\nrewards, feedback', kind='cyl', size=11.5)
    d.node('fs', 340, 675, 170, 72, '*File storage\nservice and\nprofile photos', kind='cyl', size=11.5)

    d.edge([(255, 160), (255, 220)], arrow='both')
    d.text(262, 190, 'HTTPS requests  /  server-rendered HTML', size=11, anchor='start', bg=True)
    d.edge([(255, 584), (255, 640)], arrow='both')
    d.text(262, 612, 'Eloquent ORM only; the browser never reaches the database', size=11, anchor='start', bg=True)

    d.node('g', 575, 242, 135, 50, '*Google Sign-In\n(OAuth)', kind='extdash', size=11.5)
    d.node('m', 575, 320, 135, 50, '*Mail server\n(SMTP)', kind='extdash', size=11.5)
    d.node('ai', 575, 486, 135, 60, '*Google Gemini API\n(HTTPS, JSON)', kind='extdash', size=11.5)
    d.edge([d.p('mw', 'r'), d.p('g', 'l')], arrow='both')
    d.text(548, 257, 'identity', size=10.5, bg=True)
    d.edge([(535, 345), d.p('m', 'l')])
    d.text(555, 335, 'emails', size=10.5, bg=True)
    d.edge([(505, 506), (575, 506)])
    d.edge([(575, 528), (505, 528)])
    d.text(642, 572, 'rating + redacted\ncomment out;\nlabel + confidence\nback', size=10.5)
    d.save(OUT, '06-three-tier-architecture')


class Dfd:
    """Row-based DFD helpers: externals left, processes centre, stores right."""
    EX, EW, PX, PW, SX, SW = 10, 112, 250, 160, 515, 180

    def __init__(self, d):
        self.d = d

    def ext(self, eid, y, label, dup=False, h=34):
        self.d.node(eid, self.EX, y, self.EW, h, label, kind='ext', size=11.5, tag='dup' if dup else None)

    def proc(self, pid, y, num, label, h=62):
        self.d.node(pid, self.PX, y, self.PW, h, label, kind='proc', tag=num, size=11.5)

    def store(self, sid, y, tag, label, dup=False):
        self.d.node(sid, self.SX, y, self.SW, 32, label, kind='store', tag=tag, size=11)
        if dup:
            self.d.front.append(f'<line x1="{self.SX + 6}" y1="{y}" x2="{self.SX + 6}" y2="{y + 32}" stroke="#333" stroke-width="1.2"/>')

    def hline(self, x1, x2, y, label, above=True):
        """Straight horizontal flow from x1 to x2 at height y, label above or below."""
        self.d.edge([(x1, y), (x2, y)])
        self.d.text((x1 + x2) / 2, y - 8 if above else y + 10, label, size=10.5, bg=True)


def fig07_dfd0():
    d = Diagram(720, 960, size=12)
    f = Dfd(d)
    ER, PL, PR, SL = f.EX + f.EW, f.PX, f.PX + f.PW, f.SX

    # 1.0 accounts and settings
    f.ext('cust1', 30, 'Customer')
    f.ext('adm1', 80, 'Administrator')
    f.proc('p1', 30, '1.0', 'Maintain accounts\nand settings', h=90)
    f.store('d1', 35, 'D1', 'Users and profiles')
    f.store('d2', 80, 'D2', 'Services, add-ons, staff\nschedules, operating hours')
    f.hline(ER, PL, 47, 'account details')
    f.hline(ER, PL, 97, 'staff, services, hours', above=False)
    f.hline(PR, SL, 51, 'user records')
    f.hline(PR, SL, 96, 'settings')

    # 2.0 booking
    f.ext('cust2', 165, 'Customer', dup=True, h=46)
    f.ext('rec2', 225, 'Receptionist')
    f.proc('p2', 165, '2.0', 'Schedule and book\nappointments', h=94)
    f.store('d2b', 170, 'D2', 'Services, add-ons, staff\nschedules, operating hours', dup=True)
    f.store('d3', 220, 'D3', 'Appointments')
    f.hline(ER, PL, 177, 'booking request')
    d.edge([(PL, 202), (ER, 202)])
    d.text((ER + PL) / 2, 212, 'times, confirmation', size=10.5, bg=True)
    f.hline(ER, PL, 242, 'walk-in booking, outcome')
    d.edge([(SL, 186), (PR, 186)])
    d.text((PR + SL) / 2, 178, 'availability rules', size=10.5, bg=True)
    f.hline(PR, SL, 236, 'appointment')

    # 3.0 payments
    f.ext('rec3', 320, 'Receptionist', dup=True)
    f.ext('ther3', 370, 'Therapist')
    f.proc('p3', 315, '3.0', 'Record payments\nand commissions', h=90)
    f.store('d4', 340, 'D4', 'Transactions and\ncommissions')
    f.hline(ER, PL, 337, 'payment details')
    d.edge([(PL, 387), (ER, 387)])
    d.text((ER + PL) / 2, 400, 'commission summary', size=10.5, bg=True)
    d.edge([(SL + 50, 252), (SL + 50, 290), (PR - 30, 290), (PR - 30, 315)])
    d.text(SL - 25, 282, 'completed visits', size=10.5, bg=True)
    f.hline(PR, SL, 356, 'transactions')

    # 4.0 attendance
    f.ext('ther4', 455, 'Therapist', dup=True)
    f.ext('rec4', 505, 'Receptionist', dup=True)
    f.proc('p4', 450, '4.0', 'Track staff\nattendance', h=90)
    f.store('d5', 480, 'D5', 'Attendance')
    f.hline(ER, PL, 472, 'QR scan')
    d.edge([(PL, 522), (ER, 522)])
    d.text((ER + PL) / 2, 535, 'rotating QR code', size=10.5, bg=True)
    f.hline(PR, SL, 496, 'time in / out')

    # 5.0 rewards
    f.ext('adm5', 600, 'Administrator', dup=True)
    f.ext('cust5', 650, 'Customer', dup=True)
    f.proc('p5', 595, '5.0', 'Issue RFM\nrewards', h=90)
    f.store('d6', 640, 'D6', 'Reward rules\nand vouchers')
    f.hline(ER, PL, 617, 'group rules')
    d.edge([(PL, 667), (ER, 667)])
    d.text((ER + PL) / 2, 680, 'add-on voucher', size=10.5, bg=True)
    d.edge([(SL, 362), (SL - 12, 362), (SL - 12, 610), (PR, 610)])
    d.text(SL - 4, 585, 'paid, completed visits from D4', size=10.5, anchor='start', bg=True)
    f.hline(PR, SL, 656, 'vouchers')

    # 6.0 feedback
    f.ext('cust6', 735, 'Customer', dup=True)
    f.ext('ai6', 785, 'Gemini API', h=40)
    f.proc('p6', 730, '6.0', 'Collect and classify\nfeedback', h=96)
    f.store('d7', 762, 'D7', 'Feedback and\nsentiment')
    f.hline(ER, PL, 752, 'rating and review')
    d.edge([(PL, 795), (ER, 795)])
    d.text((ER + PL) / 2, 785, 'redacted comment', size=10.5, bg=True)
    f.hline(ER, PL, 815, 'label, confidence', above=False)
    f.hline(PR, SL, 778, 'label, topics')

    # 7.0 reports
    f.ext('adm7', 880, 'Administrator', dup=True)
    f.proc('p7', 865, '7.0', 'Generate reports', h=64)
    d.edge([(PL, 897), (ER, 897)])
    d.text((ER + PL) / 2, 887, 'reports, exports', size=10.5, bg=True)
    bus = 710
    for sid in ('d3', 'd4', 'd5', 'd6', 'd7'):
        x, y, w, h, _ = d.nodes[sid]
        d.edge([(x + w, y + h / 2), (bus, y + h / 2)], arrow=None)
    d.edge([(bus, 236), (bus, 897), (PR, 897)])
    d.text(560, 887, 'records from D3 to D7', size=10.5, bg=True)
    d.save(OUT, '07-dfd-level-0')


def fig08_dfd1():
    d = Diagram(720, 640, size=12)
    f = Dfd(d)
    ER, PL, PR, SL = f.EX + f.EW, f.PX, f.PX + f.PW, f.SX

    f.ext('r1', 30, 'Receptionist')
    f.proc('p31', 20, '3.1', 'Record visit\noutcome', h=54)
    f.store('d3', 31, 'D3', 'Appointments')
    f.hline(ER, PL, 47, 'completed or no-show')
    f.hline(PR, SL, 47, 'status and log')

    f.ext('r2', 130, 'Receptionist', dup=True, h=70)
    f.proc('p32', 115, '3.2', 'Quote the bill', h=110)
    f.store('d3b', 125, 'D3', 'Appointments', dup=True)
    f.store('d2', 165, 'D2', 'Services and add-ons')
    f.store('d8', 205, 'D8', 'Memberships and\ngift vouchers')
    f.hline(ER, PL, 147, 'card, gift voucher')
    d.edge([(PL, 185), (ER, 185)])
    d.text((ER + PL) / 2, 175, 'amount due', size=10.5, bg=True)
    for y, lab in ((141, 'service, add-ons'), (181, 'prices'), (221, 'discounts, balance')):
        d.edge([(SL, y), (PR, y)])
        d.text((PR + SL) / 2, y - 8, lab, size=10.5, bg=True)

    f.ext('r3', 290, 'Receptionist', dup=True)
    f.proc('p33', 265, '3.3', 'Record payment', h=80)
    f.store('d4a', 270, 'D4a', 'Transactions and\nadjustments')
    f.store('d8b', 315, 'D8', 'Memberships and\ngift vouchers', dup=True)
    d.edge([d.p('p32', 'b'), d.p('p33', 't')])
    d.text(PL + f.PW / 2 + 8, 245, 'quote', size=10.5, anchor='start', bg=True)
    f.hline(ER, PL, 307, 'cash payment')
    f.hline(PR, SL, 286, 'paid transaction')
    f.hline(PR, SL, 331, 'perk use, redemption', above=False)
    d.node('p5', SL + 10, 380, f.SW - 20, 50, 'Issue RFM rewards', kind='proc', tag='5.0', size=11)
    d.edge([(380, 345), (380, 410), (SL + 10, 410)], dash=True)
    d.text(386, 398, 'paid, completed visit', size=10.5, anchor='start', bg=True)

    f.ext('t4', 465, 'Therapist')
    f.proc('p34', 440, '3.4', 'Compute therapist\ncommission', h=84)
    f.store('d4a2', 445, 'D4a', 'Transactions and\nadjustments', dup=True)
    f.store('d4b', 490, 'D4b', 'Therapist\ncommissions')
    d.edge([(SL, 461), (PR, 461)])
    d.text((PR + SL) / 2, 453, 'paid amount', size=10.5, bg=True)
    f.hline(PR, SL, 506, 'commission', above=False)
    d.edge([(PL, 482), (ER, 482)])
    d.text((ER + PL) / 2, 472, 'commission summary', size=10.5, bg=True)

    f.ext('a5', 570, 'Administrator')
    f.proc('p35', 555, '3.5', 'Pay out\ncommissions', h=64)
    f.store('d4b2', 571, 'D4b', 'Therapist\ncommissions', dup=True)
    f.hline(ER, PL, 587, 'payout')
    f.hline(PR, SL, 587, 'paid status')
    d.text(360, 630, 'The commission rate defaults to 22% of the paid service amount.', size=10.5, italic=True)
    d.save(OUT, '08-dfd-level-1-payments')


def fig09_booking():
    d = Diagram(720, 1000, size=12)
    A, AW = 60, 280
    cx = A + AW / 2
    d.node('s', cx - 120, 10, 240, 34, '*Customer opens the booking wizard', kind='term', size=11.5)
    d.node('a1', A, 62, AW, 40, 'Choose service, add-ons and an\noptional add-on voucher', size=11.5)
    d.node('a2', A, 120, AW, 46, 'System lists 30-minute start times inside\noperating hours when a therapist is free', size=11.5)
    d.node('a3', A, 184, AW, 34, 'Customer picks a time and submits', size=11.5)
    decs = [
        ('q1', 236, 64, 'More than 3 submissions\nin 10 minutes?', 'Yes'),
        ('q2', 318, 78, 'Blocked by an administrator,\n2 no-shows in 90 days, or\n2 upcoming bookings?', 'Yes'),
        ('q3', 414, 70, 'Future 30-minute start\nthat ends by closing time?', 'No'),
        ('q4', 518, 64, 'Overlaps one of the\ncustomer\'s appointments?', 'Yes'),
        ('q5', 600, 70, 'Qualified therapist free?\n(preferred, else least busy)', 'No'),
    ]
    for qid, y, h, label, _ in decs:
        d.node(qid, A, y, AW, h, label, kind='dec', size=11)
    d.node('a4', A, 690, AW, 40, 'Reserve the voucher and save the\nappointment as Confirmed', size=11.5)
    d.node('lock', A - 45, 506, AW + 90, 246, 'one database transaction with row locks', kind='lockgroup', size=12)
    d.node('a5', A, 768, AW, 40, 'Log the status and alert\nthe front desk', size=11.5)
    d.node('x', 430, 236, 170, 434, 'Show the reason\nin the booking\ndialog; the\ncustomer picks\nanother time', kind='stop', size=11.5)
    chain = ['s', 'a1', 'a2', 'a3', 'q1', 'q2', 'q3', 'q4', 'q5', 'a4', 'a5']
    for a, b in zip(chain, chain[1:]):
        d.edge([d.p(a, 'b'), d.p(b, 't')])
    for qid, y, h, _, fail in decs:
        d.edge([d.p(qid, 'r'), (430, y + h / 2)])
        d.text((A + AW + 430) / 2 + 12, y + h / 2 - 9, fail, size=10.5, bg=True)
        d.text(cx + 14, y + h + 8, 'No' if fail == 'Yes' else 'Yes', size=10.5, anchor='start', bg=True)
    d.edge([d.p('x', 't'), (515, 143), d.p('a2', 'r')])
    d.text(520, 165, 'try again', size=10.5, anchor='start', bg=True)

    d.node('v', A + 40, 826, AW - 80, 62, 'Visit outcome', kind='dec', size=11.5)
    d.edge([d.p('a5', 'b'), d.p('v', 't')])
    d.node('pay', 420, 839, 250, 36, 'Receptionist records the paid transaction', size=11)
    d.node('rw', 420, 893, 250, 36, 'Commission earned; RFM reward checked', size=11)
    d.node('ns', A, 908, AW, 36, 'No-show or cancelled: reserved voucher released', size=11)
    d.node('e', 485, 952, 120, 28, '*End', kind='term', size=11)
    d.edge([d.p('v', 'r'), d.p('pay', 'l')])
    d.text(380, 848, 'Completed', size=10.5, bg=True)
    d.edge([d.p('pay', 'b'), d.p('rw', 't')])
    d.edge([d.p('rw', 'b'), d.p('e', 't')])
    d.edge([d.p('v', 'b'), d.p('ns', 't')])
    d.text(cx + 10, 898, 'No-show / cancelled', size=10.5, anchor='start', bg=True)
    d.edge([d.p('ns', 'b'), (cx, 966), d.p('e', 'l')])
    d.save(OUT, '09-booking-flow')


def fig11_rfm():
    d = Diagram(720, 850, size=12)
    A, AW = 60, 320
    cx = A + AW / 2
    d.node('s', cx - 100, 10, 200, 32, '*A transaction is saved', kind='term', size=11.5)
    d.node('q1', A, 60, AW, 64, 'Paid, and the appointment\nis Completed?', kind='dec', size=11)
    d.node('q2', A, 142, AW, 74, 'Customer already has an unused\nor reserved add-on voucher?', kind='dec', size=11)
    d.node('c1', A, 236, AW, 40, 'Compute Recency, Frequency and Monetary\nvalues from the customer\'s paid visits', size=11.5)
    d.node('c2', A, 292, AW, 40, 'Score R, F and M from 1 to 5 by percentile\nrank among all customers with paid visits', size=11.5)
    d.node('c3', A, 348, AW, 34, 'Check active customer-group rules by priority', size=11.5)
    d.node('q3', A, 400, AW, 70, 'First rule whose R, F and M\nscore ranges all match?', kind='dec', size=11)
    d.node('q4', A, 488, AW, 66, 'That rule offers an active\ncomplimentary add-on?', kind='dec', size=11)
    d.node('i', A, 574, AW, 40, 'Issue the add-on voucher\n(valid 90 days by default)', size=11.5)
    d.node('u', A, 630, AW, 40, 'Customer selects it when booking;\nthe voucher is reserved', size=11.5)
    d.node('v', A + 60, 688, AW - 120, 56, 'Visit outcome', kind='dec', size=11.5)
    d.node('rd', A + 30, 790, AW - 60, 40, '*Redeemed on the\n*completed visit', kind='term', size=11.5)
    d.node('ex', 450, 690, 230, 52, '*Released back to the customer;\n*expires if never used', kind='term', size=11.5)
    d.node('no', 470, 60, 190, 494, '*No voucher\n*issued', kind='none', size=12)
    chain = ['s', 'q1', 'q2', 'c1', 'c2', 'c3', 'q3', 'q4', 'i', 'u', 'v', 'rd']
    for a, b in zip(chain, chain[1:]):
        d.edge([d.p(a, 'b'), d.p(b, 't')])
    for qid, fail in (('q1', 'No'), ('q2', 'Yes'), ('q3', 'No rule'), ('q4', 'No')):
        x, y, w, h, _ = d.nodes[qid]
        d.edge([d.p(qid, 'r'), (470, y + h / 2)])
        d.text(425, y + h / 2 - 9, fail, size=10.5, bg=True)
        d.text(cx + 12, y + h + 8, 'Yes' if fail != 'Yes' else 'No', size=10.5, anchor='start', bg=True)
    d.text(cx + 12, 752, 'Completed', size=10.5, anchor='start', bg=True)
    d.edge([d.p('v', 'r'), d.p('ex', 'l')])
    d.text(385, 700, 'Cancelled / no-show', size=10.5, bg=True)
    d.save(OUT, '11-rfm-reward-flow')


def fig10_sentiment():
    d = Diagram(720, 660, size=12)
    d.node('s', 160, 10, 400, 40, '*Customer submits a 1 to 5 star rating and an\n*optional comment for a completed visit', kind='term', size=11.5)
    d.node('rules', 40, 90, 250, 58, '*Rules classifier\nrating and keyword topics give a\nlabel and topic evidence', size=11.5)
    d.node('red', 430, 90, 250, 58, '*Privacy step\nremove emails, links, phone and booking\nnumbers; keep 1,000 characters', size=11)
    d.node('ai', 430, 176, 250, 54, '*Google Gemini API\nreturns a label and confidence\n(5-second timeout)', kind='extdash', size=11.5)
    d.edge([d.p('s', 'b', 0.25), d.p('rules', 't')])
    d.edge([d.p('s', 'b', 0.75), d.p('red', 't')])
    d.edge([d.p('red', 'b'), d.p('ai', 't')])
    d.node('q', 190, 252, 340, 92, 'Mode is "model", Gemini answered,\nand confidence is at least 0.80?', kind='dec', size=11.5)
    d.edge([d.p('rules', 'b'), (165, 298), d.p('q', 'l')])
    d.edge([d.p('ai', 'b'), (555, 298), d.p('q', 'r')])
    d.node('m', 60, 380, 230, 44, '*Use the Gemini label\nsource recorded as "model"', size=11.5)
    d.node('f', 430, 380, 230, 44, '*Fall back to the rules label\nsource recorded as "rules"', size=11.5)
    d.edge([d.p('q', 'b'), (360, 360), (175, 360), d.p('m', 't')])
    d.edge([(360, 360), (545, 360), d.p('f', 't')])
    d.text(240, 352, 'Yes', size=10.5, bg=True)
    d.text(480, 352, 'No, or Gemini error', size=10.5, bg=True)
    d.node('st', 130, 462, 460, 70, '*Save on the feedback record\nlabel, score, source, confidence, version and evidence;\nsave matched topics and both candidate runs (rules and model)', size=11.5)
    d.edge([d.p('m', 'b'), (175, 445), (300, 445), d.p('st', 't', 0.37)])
    d.edge([d.p('f', 'b'), (545, 445), (420, 445), d.p('st', 't', 0.63)])
    d.node('e', 160, 562, 400, 40, '*Shown on the Feedback page and in Reports', kind='term', size=11.5)
    d.edge([d.p('st', 'b'), d.p('e', 't')])
    d.text(360, 630, 'Administrators can read each candidate run and record a manual review.\nNo automatic review queue is created.', size=11, italic=True)
    d.save(OUT, '10-sentiment-flow')


def fig12a_erd():
    d = Diagram(720, 680, size=12)
    E, R = d.entity, d.rel
    E('users', 270, 10, 180, 'users', [('id', 'PK'), ('name', ''), ('email', 'UK'), ('google_id', ''), ('role', ''), ('is_active', '')])
    E('cp', 20, 30, 190, 'customer_profiles', [('id', 'PK'), ('user_id', 'FK'), ('customer_code', 'UK'), ('online_booking_blocked_at', ''), ('guest_name', '')])
    E('sp', 270, 170, 180, 'staff_profiles', [('id', 'PK'), ('user_id', 'FK'), ('staff_type', ''), ('position', ''), ('is_bookable', '')])
    E('svc', 530, 170, 170, 'services', [('id', 'PK'), ('name', ''), ('duration_minutes', ''), ('price', '')])
    E('ss', 530, 300, 170, 'staff_services', [('staff_profile_id', 'FK'), ('service_id', 'FK')])
    E('ws', 20, 190, 190, 'staff_weekly_schedules', [('id', 'PK'), ('staff_profile_id', 'FK'), ('day_of_week', ''), ('start_time', ''), ('end_time', '')])
    E('ex', 20, 320, 190, 'staff_schedule_exceptions', [('id', 'PK'), ('staff_profile_id', 'FK'), ('exception_date', ''), ('exception_type', '')])
    E('wk', 20, 470, 170, 'staff_schedule_weeks', [('id', 'PK'), ('week_start_date', ''), ('published_at', '')])
    E('sh', 240, 470, 190, 'staff_schedule_shifts', [('id', 'PK'), ('staff_schedule_week_id', 'FK'), ('staff_profile_id', 'FK'), ('schedule_date', ''), ('start_time / end_time', '')])
    E('att', 270, 330, 180, 'staff_attendances', [('id', 'PK'), ('staff_profile_id', 'FK'), ('attendance_date', ''), ('time_in_at', ''), ('time_out_at', '')])
    E('sr', 500, 420, 200, 'attendance_scan_requests', [('id', 'PK'), ('staff_profile_id', 'FK'), ('qr_bucket', ''), ('scanned_at', ''), ('status', '')])
    E('ev', 500, 545, 200, 'staff_attendance_events', [('id', 'PK'), ('staff_attendance_id', 'FK'), ('scan_request_id', 'FK'), ('event_type', ''), ('occurred_at', '')])
    R([(270, 60), (210, 60)], 'one', 'zero_one', label='has', at=(240, 52))
    R([(360, 116), (360, 170)], 'one', 'zero_one', label='has', at=(378, 142))
    R([(450, 225), (490, 225), (490, 320), (530, 320)], 'one', 'zero_many', label='qualified for', at=(490, 268))
    R([(615, 250), (615, 300)], 'one', 'zero_many')
    R([(270, 200), (210, 225)], 'one', 'zero_many', label='works', at=(240, 200))
    R([(270, 240), (230, 240), (230, 360), (210, 360)], 'one', 'zero_many', label='has', at=(230, 300))
    R([(360, 260), (360, 330)], 'one', 'zero_many', label='records', at=(384, 296))
    R([(190, 510), (240, 510)], 'one', 'zero_many')
    d.text(215, 494, 'contains', size=10)
    R([(450, 250), (462, 250), (462, 450), (500, 450)], 'one', 'zero_many', label='submits', at=(462, 300))
    R([(445, 429), (445, 600), (500, 600)], 'one', 'zero_many', label='has', at=(470, 590))
    R([(600, 509), (600, 545)], 'zero_one', 'zero_many', label='produces', at=(640, 528))
    d.text(360, 668, 'staff_schedule_shifts also link to staff_profiles (staff_profile_id). Framework tables (sessions, cache, jobs) are omitted.', size=10, italic=True)
    d.save(OUT, '12a-erd-accounts-staff-attendance')


def fig12b_erd():
    d = Diagram(720, 720, size=12)
    E, R = d.entity, d.rel
    E('cp', 20, 10, 170, 'customer_profiles', [('id', 'PK'), ('user_id', 'FK'), ('customer_code', 'UK')])
    E('svc', 275, 10, 170, 'services', [('id', 'PK'), ('name', ''), ('price', '')])
    E('sp', 530, 10, 170, 'staff_profiles', [('id', 'PK'), ('user_id', 'FK'), ('position', '')])
    E('ap', 250, 130, 220, 'appointments', [('id', 'PK'), ('appointment_number', 'UK'), ('customer_profile_id', 'FK'), ('service_id', 'FK'), ('staff_profile_id', 'FK'), ('promotion_suggestion_id', 'FK'), ('scheduled_start_at', ''), ('scheduled_end_at', ''), ('status', '')])
    E('aa', 20, 150, 170, 'appointment_addons', [('id', 'PK'), ('appointment_id', 'FK'), ('addon_code', ''), ('price', '')])
    E('addons', 20, 270, 170, 'addons', [('id', 'PK'), ('code', 'UK'), ('price', ''), ('is_active', '')])
    E('log', 530, 150, 170, 'appointment_status_logs', [('id', 'PK'), ('appointment_id', 'FK'), ('from_status', ''), ('to_status', '')])
    E('tx', 250, 340, 220, 'transactions', [('id', 'PK'), ('transaction_number', 'UK'), ('appointment_id', 'FK'), ('customer_profile_id', 'FK'), ('customer_membership_id', 'FK'), ('gift_voucher_id', 'FK'), ('gross_amount', ''), ('discount_amount', ''), ('amount_due', ''), ('payment_status', '')])
    E('adj', 20, 400, 170, 'transaction_adjustments', [('id', 'PK'), ('transaction_id', 'FK'), ('type', ''), ('amount', '')])
    E('tc', 530, 300, 170, 'therapist_commissions', [('id', 'PK'), ('staff_profile_id', 'FK'), ('transaction_id', 'FK'), ('basis_amount', ''), ('commission_rate', ''), ('commission_amount', ''), ('status', '')])
    E('mt', 20, 560, 170, 'membership_tiers', [('id', 'PK'), ('name', ''), ('service_discount_percent', ''), ('validity_months', '')])
    E('cm', 250, 580, 220, 'customer_memberships', [('id', 'PK'), ('customer_profile_id', 'FK'), ('membership_tier_id', 'FK'), ('card_number', 'UK'), ('valid_until', '')])
    E('gv', 530, 500, 170, 'gift_vouchers', [('id', 'PK'), ('code', 'UK'), ('value', ''), ('status', ''), ('redeemed_transaction_id', 'FK')])
    R([(190, 50), (230, 50), (230, 175), (250, 175)], 'one', 'zero_many', label='books', at=(230, 110))
    R([(360, 69), (360, 130)], 'one', 'zero_many', label='booked as', at=(392, 100))
    R([(615, 69), (615, 100), (490, 100), (490, 190), (470, 190)], 'zero_one', 'zero_many', label='assigned to', at=(550, 92))
    R([(250, 215), (190, 215)], 'one', 'zero_many', label='includes', at=(220, 205))
    R([(105, 270), (105, 233)], 'one', 'zero_many', label='by code', at=(135, 252), dash=True)
    R([(470, 225), (530, 225)], 'one', 'zero_many', label='logs', at=(500, 215))
    R([(360, 289), (360, 340)], 'zero_one', 'zero_many', label='billed by', at=(392, 315))
    R([(250, 440), (190, 440)], 'one', 'zero_many', label='has', at=(220, 430))
    R([(470, 380), (530, 380)], 'one', 'zero_many', label='earns', at=(500, 370))
    R([(360, 504), (360, 580)], 'zero_many', 'zero_one', label='discounted by', at=(405, 545))
    R([(190, 610), (250, 610)], 'one', 'zero_many', label='defines', at=(220, 600))
    R([(470, 440), (500, 440), (500, 540), (530, 540)], 'zero_one', 'zero_one', label='pays with', at=(500, 490))
    R([(20, 70), (8, 70), (8, 668), (250, 668)], 'one', 'zero_many')
    d.text(12, 520, 'holds', size=10, anchor='start', bg=True)
    d.text(360, 708, 'therapist_commissions also link to staff_profiles (staff_profile_id) and appointments (appointment_id).', size=10, italic=True)
    d.save(OUT, '12b-erd-appointments-payments')


def fig12c_erd():
    d = Diagram(720, 640, size=12)
    E, R = d.entity, d.rel
    E('cp', 20, 10, 170, 'customer_profiles', [('id', 'PK')])
    E('svc', 275, 10, 170, 'services', [('id', 'PK')])
    E('ap', 520, 10, 170, 'appointments', [('id', 'PK'), ('promotion_suggestion_id', 'FK'), ('status', '')])
    E('fb', 250, 130, 220, 'feedback', [('id', 'PK'), ('appointment_id', 'FK'), ('customer_profile_id', 'FK'), ('service_id', 'FK'), ('rating', ''), ('comment', ''), ('is_anonymous', ''), ('sentiment_label', ''), ('sentiment_source', ''), ('sentiment_confidence', '')])
    E('ft', 20, 140, 170, 'feedback_topics', [('id', 'PK'), ('feedback_id', 'FK'), ('topic_key', ''), ('polarity', '')])
    E('fr', 530, 120, 170, 'feedback_sentiment_runs', [('id', 'PK'), ('feedback_id', 'FK'), ('source', ''), ('label', ''), ('confidence', ''), ('is_authoritative', '')])
    E('fa', 530, 250, 170, 'feedback_annotations', [('id', 'PK'), ('feedback_id', 'FK'), ('reviewer_id', 'FK'), ('label', ''), ('status', '')])
    E('seg', 20, 300, 220, 'rfm_segments', [('id', 'PK'), ('name', ''), ('priority', ''), ('r/f/m_score_min, _max', ''), ('addon_code', ''), ('recommended_membership_tier_id', 'FK'), ('is_active', '')])
    E('ps', 290, 330, 190, 'promotion_suggestions', [('id', 'PK'), ('customer_profile_id', 'FK'), ('rfm_segment_id', 'FK'), ('r_score, f_score, m_score', ''), ('addon_code', ''), ('status', ''), ('expires_at', '')])
    E('mt', 20, 520, 220, 'membership_tiers', [('id', 'PK'), ('name', '')])
    E('ohr', 520, 410, 180, 'operating_hour_rules', [('id', 'PK'), ('type', ''), ('starts_on / ends_on', ''), ('start_time / end_time', ''), ('reason', '')])
    E('set', 520, 520, 180, 'application_settings', [('id', 'PK'), ('opens_at', ''), ('closes_at', ''), ('promotion_voucher_validity_days', '')])
    R([(105, 49), (105, 90), (230, 90), (230, 160), (250, 160)], 'one', 'zero_many', label='writes', at=(160, 82))
    R([(360, 49), (360, 130)], 'one', 'zero_many', label='about', at=(386, 90))
    R([(605, 79), (605, 100), (490, 100), (490, 170), (470, 170)], 'one', 'zero_one', label='rated by', at=(550, 92))
    R([(250, 200), (190, 200)], 'one', 'zero_many', label='has', at=(220, 190))
    R([(470, 200), (530, 200)], 'one', 'zero_many', label='runs', at=(500, 190))
    R([(470, 280), (530, 280)], 'one', 'zero_many', label='reviewed in', at=(500, 270))
    R([(240, 380), (290, 380)], 'one', 'zero_many')
    d.text(265, 366, 'matched', size=10)
    R([(130, 520), (130, 412)], 'zero_one', 'zero_many', label='recommends', at=(172, 470))
    R([(480, 384), (708, 384), (708, 40), (690, 40)], 'zero_one', 'zero_many', label='reserved for', at=(600, 376))
    d.text(610, 400, 'Booking-hour rules (no foreign keys)', size=10, italic=True)
    d.text(360, 630, 'promotion_suggestions also link to customer_profiles (customer_profile_id).', size=10, italic=True)
    d.save(OUT, '12c-erd-feedback-rewards-settings')


FIGS = [fig01_ipo, fig04_context, fig05_use_case, fig06_architecture, fig07_dfd0, fig08_dfd1, fig09_booking, fig10_sentiment, fig11_rfm, fig12a_erd, fig12b_erd, fig12c_erd]

if __name__ == '__main__':
    for f in FIGS:
        if not ONLY or any(f.__name__.startswith('fig' + p) for p in ONLY):
            f()
            print('built', f.__name__)
