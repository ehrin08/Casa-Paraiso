"""Tiny fixed-layout SVG toolkit for the manuscript diagrams.

Every figure is drawn at 720 units wide, which maps to the 6-inch text width of the
manuscript page, so a 12-unit font prints at about 7.2 pt and 13 units at about 7.8 pt.
"""
import html
import math
import pathlib
import subprocess

FONT = 'Arial, Helvetica, sans-serif'
INK = '#1a1a1a'
LINE = '#333333'
GREEN = '#2f4f3a'
GREEN_FILL = '#e7efe9'
SAND_FILL = '#f6f4ee'
AMBER = '#9a7b2f'
AMBER_FILL = '#fdf6e3'
RED = '#a33a2a'
RED_FILL = '#fbe9e7'
GREY = '#777777'
GREY_FILL = '#eeeeee'
CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe'


def esc(s):
    return html.escape(s, quote=True)


class Diagram:
    def __init__(self, width, height, size=12.5):
        self.w, self.h, self.size = width, height, size
        self.back, self.front, self.nodes = [], [], {}

    # ---------- text ----------
    def text(self, x, y, label, size=None, anchor='middle', weight='normal', fill=INK, bg=False, italic=False, layer=None):
        size = size or self.size
        lines = label.split('\n')
        lh = size * 1.22
        top = y - (len(lines) - 1) * lh / 2
        out = layer if layer is not None else self.front
        if bg:
            wid = max(len(l) for l in lines) * size * 0.53 + 6
            left = {'middle': x - wid / 2, 'start': x - 3, 'end': x - wid + 3}[anchor]
            out.append(f'<rect x="{left:.1f}" y="{top - size * 0.85:.1f}" width="{wid:.1f}" height="{len(lines) * lh + 2:.1f}" fill="#ffffff" opacity="0.92"/>')
        spans = []
        for i, line in enumerate(lines):
            w = weight
            if line.startswith('*'):
                line, w = line[1:], 'bold'
            spans.append(f'<tspan x="{x:.1f}" y="{top + i * lh + size * 0.35:.1f}" font-weight="{w}">{esc(line)}</tspan>')
        style = ' font-style="italic"' if italic else ''
        out.append(f'<text font-family="{FONT}" font-size="{size}" text-anchor="{anchor}" fill="{fill}"{style}>{"".join(spans)}</text>')

    # ---------- nodes ----------
    def node(self, nid, x, y, w, h, label='', kind='box', size=None, tag=None):
        s = []
        cx, cy = x + w / 2, y + h / 2
        fill_text = INK
        if kind == 'box':
            s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="#fff" stroke="{GREEN}" stroke-width="1.3"/>')
        elif kind == 'ext':
            s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="#fff" stroke="{GREEN}" stroke-width="2.2"/>')
            label = '*' + label.replace('\n', '\n*')
            if tag == 'dup':
                s.append(f'<line x1="{x}" y1="{y + 10}" x2="{x + 10}" y2="{y}" stroke="{GREEN}" stroke-width="1.6"/>')
        elif kind == 'extdash':
            s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{AMBER_FILL}" stroke="{AMBER}" stroke-width="1.4" stroke-dasharray="5 3"/>')
        elif kind == 'proc':
            s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="12" fill="{GREEN_FILL}" stroke="{GREEN}" stroke-width="1.6"/>')
            if tag:
                s.append(f'<line x1="{x}" y1="{y + 20}" x2="{x + w}" y2="{y + 20}" stroke="{GREEN}" stroke-width="1"/>')
                self.text(cx, y + 10.5, tag, size=(size or self.size) - 0.5, weight='bold', layer=s)
                cy = y + 20 + (h - 20) / 2
        elif kind == 'term':
            s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{min(h / 2, 22)}" fill="{GREEN}" stroke="{GREEN}"/>')
            fill_text = '#ffffff'
        elif kind == 'none':
            s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="14" fill="{GREY_FILL}" stroke="{GREY}" stroke-width="1.3"/>')
        elif kind == 'stop':
            s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{RED_FILL}" stroke="{RED}" stroke-width="1.3"/>')
        elif kind == 'dec':
            s.append(f'<polygon points="{cx},{y} {x + w},{cy} {cx},{y + h} {x},{cy}" fill="{AMBER_FILL}" stroke="{AMBER}" stroke-width="1.4"/>')
        elif kind == 'store':
            sid = tag or ''
            s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{SAND_FILL}" stroke="none"/>')
            s.append(f'<path d="M{x + w},{y} H{x} V{y + h} H{x + w}" fill="none" stroke="{LINE}" stroke-width="1.4"/>')
            s.append(f'<line x1="{x + 34}" y1="{y}" x2="{x + 34}" y2="{y + h}" stroke="{LINE}" stroke-width="1.2"/>')
            self.text(x + 17, cy, sid, weight='bold', size=(size or self.size) - 0.5, layer=s)
            cx = x + 34 + (w - 34) / 2
        elif kind == 'cyl':
            e = 9
            s.append(f'<path d="M{x},{y + e} A{w / 2},{e} 0 0 1 {x + w},{y + e} V{y + h - e} A{w / 2},{e} 0 0 1 {x},{y + h - e} Z" fill="#fff" stroke="{GREEN}" stroke-width="1.4"/>')
            s.append(f'<path d="M{x},{y + e} A{w / 2},{e} 0 0 0 {x + w},{y + e}" fill="none" stroke="{GREEN}" stroke-width="1.4"/>')
            cy += e / 2
        elif kind == 'ellipse':
            s.append(f'<ellipse cx="{cx}" cy="{cy}" rx="{w / 2}" ry="{h / 2}" fill="#fff" stroke="{GREEN}" stroke-width="1.3"/>')
        elif kind == 'circle':
            s.append(f'<circle cx="{cx}" cy="{cy}" r="{w / 2}" fill="{GREEN_FILL}" stroke="{GREEN}" stroke-width="2.4"/>')
        elif kind == 'group':
            s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{SAND_FILL}" stroke="#8a8a7a" stroke-width="1.2"/>')
            self.text(x + 10, y + 13, label, anchor='start', weight='bold', size=size or self.size, layer=s)
            label = ''
        elif kind == 'lockgroup':
            s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="none" stroke="{GREY}" stroke-width="1.2" stroke-dasharray="6 4"/>')
            self.text(x + 6, y + h - 8, label, anchor='start', size=(size or self.size) - 1.5, italic=True, fill='#555', layer=s)
            label = ''
        elif kind == 'actor':
            hx, hy = cx, y + 9
            s.append(f'<circle cx="{hx}" cy="{hy}" r="8" fill="#fff" stroke="{GREEN}" stroke-width="1.6"/>')
            s.append(f'<path d="M{hx},{hy + 8} V{hy + 30} M{hx - 14},{hy + 16} H{hx + 14} M{hx},{hy + 30} L{hx - 11},{hy + 46} M{hx},{hy + 30} L{hx + 11},{hy + 46}" stroke="{GREEN}" stroke-width="1.6" fill="none"/>')
            self.text(cx, y + h - 7, label, weight='bold', layer=s)
            label = ''
        if label:
            self.text(cx, cy, label, size=size, fill=fill_text, layer=s)
        self.back.extend(s) if kind in ('group', 'lockgroup') else self.front.extend(s)
        self.nodes[nid] = (x, y, w, h, kind)
        return nid

    def p(self, nid, side, f=0.5):
        """Point on a node side: l, r, t, b, with fraction f along that side."""
        x, y, w, h, kind = self.nodes[nid]
        if kind == 'dec':
            return {'l': (x, y + h / 2), 'r': (x + w, y + h / 2), 't': (x + w / 2, y), 'b': (x + w / 2, y + h)}[side]
        if kind == 'ellipse' and side in 'lr':
            return (x, y + h / 2) if side == 'l' else (x + w, y + h / 2)
        return {'l': (x, y + h * f), 'r': (x + w, y + h * f), 't': (x + w * f, y), 'b': (x + w * f, y + h)}[side]

    def center(self, nid):
        x, y, w, h, _ = self.nodes[nid]
        return (x + w / 2, y + h / 2)

    # ---------- edges ----------
    def edge(self, pts, arrow='end', dash=False, label=None, at=None, anchor='middle', lsize=None, color=LINE, width=1.3, bg=True):
        d = 'M' + ' L'.join(f'{a:.1f},{b:.1f}' for a, b in pts)
        dash_attr = ' stroke-dasharray="5 4"' if dash else ''
        ends = ''
        if arrow in ('end', 'both'):
            ends += ' marker-end="url(#ah)"'
        if arrow in ('start', 'both'):
            ends += ' marker-start="url(#ahs)"'
        if arrow == 'tri':
            ends += ' marker-end="url(#tri)"'
        self.front.insert(0, f'<path d="{d}" fill="none" stroke="{color}" stroke-width="{width}"{dash_attr}{ends}/>')
        if label:
            if at is None:
                (x1, y1), (x2, y2) = pts[len(pts) // 2 - 1], pts[len(pts) // 2]
                at = ((x1 + x2) / 2, (y1 + y2) / 2)
            self.text(at[0], at[1], label, size=lsize or self.size - 1.5, anchor=anchor, bg=bg)

    def rel(self, pts, a, b, label=None, at=None, dash=False):
        """ERD relationship with crow's-foot ends; a at pts[0], b at pts[-1]."""
        self.edge(pts, arrow=None, label=label, at=at, dash=dash, lsize=self.size - 2)
        self._foot(pts[0], pts[1], a)
        self._foot(pts[-1], pts[-2], b)

    def _foot(self, p, q, kind):
        ux, uy = q[0] - p[0], q[1] - p[1]
        n = math.hypot(ux, uy) or 1
        ux, uy = ux / n, uy / n
        nx, ny = -uy, ux
        at = lambda t, o=0: (p[0] + ux * t + nx * o, p[1] + uy * t + ny * o)
        s = []
        bar = lambda t: s.append(f'<line x1="{at(t, -6)[0]:.1f}" y1="{at(t, -6)[1]:.1f}" x2="{at(t, 6)[0]:.1f}" y2="{at(t, 6)[1]:.1f}" stroke="{LINE}" stroke-width="1.3"/>')
        ring = lambda t: s.append(f'<circle cx="{at(t)[0]:.1f}" cy="{at(t)[1]:.1f}" r="4" fill="#fff" stroke="{LINE}" stroke-width="1.3"/>')
        crow = lambda: s.append(f'<path d="M{at(0, -7)[0]:.1f},{at(0, -7)[1]:.1f} L{at(12)[0]:.1f},{at(12)[1]:.1f} L{at(0, 7)[0]:.1f},{at(0, 7)[1]:.1f}" fill="none" stroke="{LINE}" stroke-width="1.3"/>')
        if kind == 'one':
            bar(7); bar(12)
        elif kind == 'zero_one':
            bar(7); ring(17)
        elif kind == 'many':
            crow()
        elif kind == 'zero_many':
            crow(); ring(18)
        elif kind == 'one_many':
            crow(); bar(16)
        self.front.extend(s)

    # ---------- ERD entity ----------
    def entity(self, nid, x, y, w, name, rows, size=10.5):
        rh, hh = 15, 20
        h = hh + rh * len(rows) + 4
        s = [f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="#fff" stroke="{GREEN}" stroke-width="1.4"/>',
             f'<rect x="{x}" y="{y}" width="{w}" height="{hh}" fill="{GREEN}" stroke="{GREEN}"/>']
        self.text(x + w / 2, y + hh / 2, name, size=size + 0.5, weight='bold', fill='#fff', layer=s)
        for i, (col, key) in enumerate(rows):
            ry = y + hh + 2 + rh * i + rh / 2
            self.text(x + 6, ry, col, size=size, anchor='start', weight='bold' if key == 'PK' else 'normal', layer=s)
            if key:
                self.text(x + w - 6, ry, key, size=size - 1, anchor='end', fill=GREY if key != 'PK' else GREEN, weight='bold', layer=s)
        self.front.extend(s)
        self.nodes[nid] = (x, y, w, h, 'entity')
        return nid

    # ---------- output ----------
    def svg(self):
        defs = (f'<defs><marker id="ah" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0,0 L10,5 L0,10 z" fill="{LINE}"/></marker>'
                f'<marker id="ahs" viewBox="0 0 10 10" refX="1" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M10,0 L0,5 L10,10 z" fill="{LINE}"/></marker>'
                f'<marker id="tri" viewBox="0 0 12 12" refX="11" refY="6" markerWidth="11" markerHeight="11" orient="auto"><path d="M0,0 L12,6 L0,12 z" fill="#fff" stroke="{LINE}" stroke-width="1.2"/></marker></defs>')
        body = ''.join(self.back) + ''.join(self.front)
        return (f'<svg xmlns="http://www.w3.org/2000/svg" width="{self.w}" height="{self.h}" viewBox="0 0 {self.w} {self.h}">'
                f'{defs}<rect width="100%" height="100%" fill="#fff"/>{body}</svg>')

    def save(self, out_dir, name, scale=4):
        out_dir = pathlib.Path(out_dir).resolve()
        out_dir.mkdir(parents=True, exist_ok=True)
        svg_path = out_dir / f'{name}.svg'
        svg_path.write_text(self.svg(), encoding='utf-8')
        page = out_dir / f'{name}.html'
        page.write_text(f'<!doctype html><html><body style="margin:0;background:#fff">{self.svg()}</body></html>', encoding='utf-8')
        subprocess.run([CHROME, '--headless=new', '--disable-gpu', '--hide-scrollbars', f'--force-device-scale-factor={scale}',
                        f'--window-size={self.w},{self.h}', f'--screenshot={out_dir / (name + ".png")}', page.resolve().as_uri()],
                       check=True, capture_output=True)
        page.unlink()
