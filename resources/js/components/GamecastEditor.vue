<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { matchClockMinute, useNow } from '@/composables/useMatchClock';
import { destroy as destroyEvent, store as storeEvent, update as updateEvent } from '@/routes/games/events';
import { update as updateStatus } from '@/routes/games/status';

interface Player {
    id: number;
    name: string;
    shirt_number: number | null;
}

interface Roster {
    team_id: number | null;
    players: Player[];
}

interface TeamRef {
    id: number;
    name: string;
    acronym: string;
}

interface EditableEvent {
    id: number;
    type: string;
    type_label: string;
    minute: number | null;
    stoppage: number | null;
    team_acronym: string | null;
    team_id: number | null;
    player_id: number | null;
    assist_player_id: number | null;
    secondary_player_id: number | null;
    player_name: string | null;
    description: string | null;
}

const props = defineProps<{
    routeArgs: { league: string; season: number; stage: number; game: number };
    status: string;
    currentMinute: number | null;
    clockStartedAt: string | null;
    homeTeam: TeamRef | null;
    awayTeam: TeamRef | null;
    rosters: { home: Roster; away: Roster };
    events: EditableEvent[];
}>();

// --- Status + clock controls ----------------------------------------------

const statusForm = useForm<{ status: string; current_minute: number | null }>({
    status: props.status,
    current_minute: props.currentMinute,
});

const statusLabels: Record<string, string> = {
    scheduled: 'Scheduled',
    live: 'Live',
    half_time: 'Half Time',
    full_time: 'Full Time',
    postponed: 'Postponed',
    cancelled: 'Cancelled',
};

const currentStatusLabel = computed(() => statusLabels[props.status] ?? props.status);

// The clock runs by itself once kicked off; this is the minute it currently
// reads, ticking locally between broadcasts.
const now = useNow();
const liveMinute = computed(() => matchClockMinute(now.value, props.status, props.currentMinute, props.clockStartedAt));
const isRunning = computed(() => props.status === 'live' && props.clockStartedAt !== null);

// `minute` is the override sent with a status change: Kick Off restarts the
// clock at 0, Resume (and the other transitions) pass null so the server keeps
// or freezes the current minute on its own.
const statusActions: { label: string; status: string; minute?: number; variant?: 'default' | 'outline' | 'destructive' | 'secondary' }[] = [
    { label: 'Kick Off', status: 'live', minute: 0 },
    { label: 'Half Time', status: 'half_time', variant: 'secondary' },
    { label: 'Resume', status: 'live', variant: 'secondary' },
    { label: 'Full Time', status: 'full_time', variant: 'outline' },
    { label: 'Postpone', status: 'postponed', variant: 'outline' },
    { label: 'Cancel', status: 'cancelled', variant: 'destructive' },
];

function applyStatus(status: string, minute?: number): void {
    statusForm.status = status;
    statusForm.current_minute = minute ?? null;
    statusForm.patch(updateStatus(props.routeArgs).url, { preserveScroll: true });
}

// Manual clock correction. Keeps the current status: a running clock re-anchors
// to the new minute and keeps ticking; a paused clock just freezes there.
const correction = ref<number | null>(null);

function setMinute(value: number | null): void {
    if (value === null) {
        return;
    }

    correction.value = null;
    applyStatus(props.status, Math.max(0, value));
}

function nudgeMinute(delta: number): void {
    setMinute((liveMinute.value ?? 0) + delta);
}

// --- Event entry / editing -------------------------------------------------

const eventTypes: { value: string; label: string }[] = [
    { value: 'goal', label: 'Goal' },
    { value: 'penalty_goal', label: 'Penalty Goal' },
    { value: 'own_goal', label: 'Own Goal' },
    { value: 'yellow_card', label: 'Yellow Card' },
    { value: 'red_card', label: 'Red Card' },
    { value: 'substitution', label: 'Substitution' },
    { value: 'var_check', label: 'VAR Check' },
    { value: 'commentary', label: 'Commentary' },
];

// When set, the form is correcting that event rather than recording a new one.
const editingId = ref<number | null>(null);
const isEditing = computed(() => editingId.value !== null);

const eventForm = useForm<{
    type: string;
    minute: number | null;
    stoppage: number | null;
    team_id: number | null;
    player_id: number | null;
    assist_player_id: number | null;
    secondary_player_id: number | null;
    description: string;
}>({
    type: 'goal',
    minute: null,
    stoppage: null,
    team_id: null,
    player_id: null,
    assist_player_id: null,
    secondary_player_id: null,
    description: '',
});

const isScoring = computed(() => ['goal', 'penalty_goal', 'own_goal'].includes(eventForm.type));
const isGoalLike = computed(() => ['goal', 'penalty_goal'].includes(eventForm.type));
const isSubstitution = computed(() => eventForm.type === 'substitution');

const selectedRoster = computed<Player[]>(() => {
    if (eventForm.team_id === props.rosters.home.team_id) {
        return props.rosters.home.players;
    }

    if (eventForm.team_id === props.rosters.away.team_id) {
        return props.rosters.away.players;
    }

    return [];
});

function transformPayload(data: Record<string, unknown>): Record<string, unknown> {
    return {
        ...data,
        minute: data.minute === null || (data.minute as string) === '' ? null : Number(data.minute),
        stoppage: data.stoppage === null || (data.stoppage as string) === '' ? null : Number(data.stoppage),
        description: data.description === '' ? null : data.description,
    };
}

function submitEvent(): void {
    if (isEditing.value) {
        eventForm.transform(transformPayload).patch(updateEvent({ ...props.routeArgs, event: editingId.value as number }).url, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        });

        return;
    }

    eventForm.transform(transformPayload).post(storeEvent(props.routeArgs).url, {
        preserveScroll: true,
        onSuccess: () => eventForm.reset('player_id', 'assist_player_id', 'secondary_player_id', 'description'),
    });
}

function startEdit(event: EditableEvent): void {
    editingId.value = event.id;
    eventForm.clearErrors();
    eventForm.type = event.type;
    eventForm.minute = event.minute;
    eventForm.stoppage = event.stoppage;
    eventForm.team_id = event.team_id;
    eventForm.player_id = event.player_id;
    eventForm.assist_player_id = event.assist_player_id;
    eventForm.secondary_player_id = event.secondary_player_id;
    eventForm.description = event.description ?? '';
}

function resetForm(): void {
    editingId.value = null;
    eventForm.clearErrors();
    eventForm.reset();
}

function removeEvent(event: EditableEvent): void {
    if (!window.confirm(`Remove this ${event.type_label.toLowerCase()}?`)) {
        return;
    }

    router.delete(destroyEvent({ ...props.routeArgs, event: event.id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingId.value === event.id) {
                resetForm();
            }
        },
    });
}

// Lifecycle markers (kick off / half time / full time) are produced by the
// status buttons, not the entry form, so they're delete-only here.
const lifecycleTypes = ['kick_off', 'half_time', 'full_time'];

function isLifecycle(event: EditableEvent): boolean {
    return lifecycleTypes.includes(event.type);
}

function eventSummary(event: EditableEvent): string {
    const minute = event.minute === null ? '' : event.stoppage ? `${event.minute}+${event.stoppage}' ` : `${event.minute}' `;
    const who = [event.team_acronym, event.player_name].filter(Boolean).join(' · ');

    return `${minute}${event.type_label}${who ? ` — ${who}` : ''}`;
}
</script>

<template>
    <div class="space-y-6 rounded-xl border bg-card p-5 shadow-sm">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Match controls</h2>
            <p class="text-xs text-muted-foreground">Owner-only · updates broadcast live</p>
        </div>

        <!-- Status + clock -->
        <div class="space-y-3">
            <div class="flex items-center justify-between rounded-lg border bg-muted/40 px-4 py-3">
                <div class="flex items-center gap-2">
                    <span v-if="isRunning" class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-live opacity-75" />
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-live" />
                    </span>
                    <span class="text-sm font-medium">{{ currentStatusLabel }}</span>
                </div>
                <span class="font-display text-3xl font-bold tabular-nums leading-none">
                    {{ liveMinute === null ? '—' : `${liveMinute}'` }}
                </span>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="action in statusActions"
                    :key="action.label"
                    type="button"
                    size="sm"
                    :variant="action.variant ?? 'default'"
                    :disabled="statusForm.processing"
                    @click="applyStatus(action.status, action.minute)"
                >
                    {{ action.label }}
                </Button>
            </div>
            <InputError :message="statusForm.errors.status" />

            <!-- Manual clock correction (the clock runs on its own otherwise). -->
            <div class="flex items-end gap-2">
                <div class="w-24">
                    <Label for="minute">Set minute</Label>
                    <Input
                        id="minute"
                        v-model.number="correction"
                        type="number"
                        min="0"
                        max="200"
                        :placeholder="liveMinute === null ? '—' : String(liveMinute)"
                        @keyup.enter="setMinute(correction)"
                    />
                </div>
                <Button type="button" size="sm" variant="secondary" :disabled="statusForm.processing || correction === null" @click="setMinute(correction)">
                    Set
                </Button>
                <Button type="button" size="sm" variant="outline" :disabled="statusForm.processing" @click="nudgeMinute(-1)">−1</Button>
                <Button type="button" size="sm" variant="outline" :disabled="statusForm.processing" @click="nudgeMinute(1)">+1</Button>
            </div>
        </div>

        <hr class="border-border" />

        <!-- Event entry / editing -->
        <form class="space-y-3" @submit.prevent="submitEvent">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">{{ isEditing ? 'Edit event' : 'Add event' }}</h3>
                <Button v-if="isEditing" type="button" size="sm" variant="ghost" @click="resetForm">Cancel</Button>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="col-span-2">
                    <Label for="event-type">Event</Label>
                    <select
                        id="event-type"
                        v-model="eventForm.type"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                    >
                        <option v-for="option in eventTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div>
                    <Label for="event-minute">Minute</Label>
                    <Input id="event-minute" v-model="eventForm.minute" type="number" min="0" max="200" />
                </div>
                <div>
                    <Label for="event-stoppage">+Stoppage</Label>
                    <Input id="event-stoppage" v-model="eventForm.stoppage" type="number" min="0" max="30" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <Label for="event-team">Team</Label>
                    <select
                        id="event-team"
                        v-model.number="eventForm.team_id"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                    >
                        <option :value="null">—</option>
                        <option v-if="homeTeam" :value="homeTeam.id">{{ homeTeam.name }}</option>
                        <option v-if="awayTeam" :value="awayTeam.id">{{ awayTeam.name }}</option>
                    </select>
                    <InputError :message="eventForm.errors.team_id" />
                </div>
                <div>
                    <Label for="event-player">{{ isSubstitution ? 'Player off' : 'Player' }}</Label>
                    <select
                        id="event-player"
                        v-model.number="eventForm.player_id"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                        :disabled="selectedRoster.length === 0"
                    >
                        <option :value="null">—</option>
                        <option v-for="player in selectedRoster" :key="player.id" :value="player.id">
                            <template v-if="player.shirt_number">#{{ player.shirt_number }} </template>{{ player.name }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="isGoalLike">
                <Label for="event-assist">Assist (optional)</Label>
                <select
                    id="event-assist"
                    v-model.number="eventForm.assist_player_id"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                    :disabled="selectedRoster.length === 0"
                >
                    <option :value="null">—</option>
                    <option v-for="player in selectedRoster" :key="player.id" :value="player.id">{{ player.name }}</option>
                </select>
            </div>

            <div v-if="isSubstitution">
                <Label for="event-on">Player on</Label>
                <select
                    id="event-on"
                    v-model.number="eventForm.secondary_player_id"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                    :disabled="selectedRoster.length === 0"
                >
                    <option :value="null">—</option>
                    <option v-for="player in selectedRoster" :key="player.id" :value="player.id">{{ player.name }}</option>
                </select>
            </div>

            <div>
                <Label for="event-description">Description (optional)</Label>
                <Input id="event-description" v-model="eventForm.description" type="text" maxlength="500" />
            </div>

            <p v-if="isScoring" class="text-xs text-muted-foreground">Goals update the scoreline automatically.</p>

            <Button type="submit" :disabled="eventForm.processing">{{ isEditing ? 'Save changes' : 'Record event' }}</Button>
        </form>

        <!-- Recorded events: correct or remove existing entries -->
        <template v-if="events.length > 0">
            <hr class="border-border" />

            <div class="space-y-2">
                <h3 class="text-sm font-semibold">Recorded events</h3>
                <ul class="divide-y rounded-md border">
                    <li
                        v-for="event in events"
                        :key="event.id"
                        class="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                        :class="{ 'bg-muted/50': editingId === event.id }"
                    >
                        <span class="min-w-0 truncate">{{ eventSummary(event) }}</span>
                        <span class="flex shrink-0 gap-1">
                            <Button v-if="!isLifecycle(event)" type="button" size="sm" variant="ghost" @click="startEdit(event)">Edit</Button>
                            <Button type="button" size="sm" variant="ghost" class="text-destructive hover:text-destructive" @click="removeEvent(event)">
                                Delete
                            </Button>
                        </span>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>
