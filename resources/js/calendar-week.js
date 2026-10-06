const timezone = 'Asia/Manila';

export function manilaDateKey(value = new Date()) {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(new Date(value));
    const part = (name) => parts.find((entry) => entry.type === name).value;
    return `${part('year')}-${part('month')}-${part('day')}`;
}

export function mondayOf(value) {
    const date = new Date(`${value}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() - ((date.getUTCDay() + 6) % 7));
    return date.toISOString().slice(0, 10);
}

export function manilaTime(value) {
    return new Intl.DateTimeFormat('en-US', {
        timeZone: timezone, hour: 'numeric', minute: '2-digit',
    }).format(new Date(value));
}

export function minutesInManila(value, day) {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone, hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(value));
    const part = (name) => Number(parts.find((entry) => entry.type === name).value);
    const offset = (Date.parse(`${manilaDateKey(value)}T00:00:00Z`) - Date.parse(`${day}T00:00:00Z`)) / 86400000;
    return offset * 1440 + part('hour') * 60 + part('minute');
}

export function therapistColor(id) {
    // Stable even when the therapist filter changes the resource collection.
    return [...String(id)].reduce((hash, character) => (hash * 31 + character.charCodeAt(0)) >>> 0, 0) % 5;
}

export function positionWeekEvents(events, day, opening = 780, closing = 1440) {
    const appointments = events.filter((event) => event.kind === 'appointment'
        && manilaDateKey(event.starts_at) === day)
        .map((event) => ({
            ...event,
            startMinutes: Math.max(opening, minutesInManila(event.starts_at, day)),
            endMinutes: Math.min(closing, minutesInManila(event.ends_at, day)),
        }))
        .filter((event) => event.endMinutes > event.startMinutes)
        .sort((a, b) => a.startMinutes - b.startMinutes || a.endMinutes - b.endMinutes || String(a.id).localeCompare(String(b.id)));
    let cluster = [];
    let clusterEnd = 0;
    const result = [];
    const finishCluster = () => {
        const laneEnds = [];
        cluster.forEach((event) => {
            let lane = laneEnds.findIndex((end) => end <= event.startMinutes);
            if (lane < 0) lane = laneEnds.length;
            laneEnds[lane] = event.endMinutes;
            event.lane = lane;
        });
        result.push(...cluster.map((event) => ({ ...event, laneCount: laneEnds.length })));
    };
    appointments.forEach((event) => {
        if (cluster.length && event.startMinutes >= clusterEnd) {
            finishCluster();
            cluster = [];
        }
        cluster.push(event);
        clusterEnd = Math.max(cluster.length === 1 ? 0 : clusterEnd, event.endMinutes);
    });
    finishCluster();
    return result;
}
