<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeEvent } from '@/routes/games/events';
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

const props = defineProps<{
    routeArgs: { league: string; season: number; stage: number; game: number };
    status: string;
    currentMinute: number | null;
    homeTeam: TeamRef | null;
    awayTeam: TeamRef | null;
    rosters: { home: Roster; away: Roster };
}>();

// --- Status controls -------------------------------------------------------

const minute = ref<number | null>(props.currentMinute);

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

const statusActions: { label: string; status: string; variant?: 'default' | 'outline' | 'destructive' | 'secondary' }[] = [
    { label: 'Kick Off', status: 'live' },
    { label: 'Half Time', status: 'half_time', variant: 'secondary' },
    { label: 'Resume', status: 'live', variant: 'secondary' },
    { label: 'Full Time', status: 'full_time', variant: 'outline' },
    { label: 'Postpone', status: 'postponed', variant: 'outline' },
    { label: 'Cancel', status: 'cancelled', variant: 'destructive' },
];

function setStatus(status: string): void {
    statusForm.status = status;
    statusForm.current_minute = minute.value;
    statusForm.patch(updateStatus(props.routeArgs).url, { preserveScroll: true });
}

// --- Event entry -----------------------------------------------------------

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

function submitEvent(): void {
    eventForm
        .transform((data) => ({
            ...data,
            minute: data.minute === null || (data.minute as unknown as string) === '' ? null : Number(data.minute),
            stoppage: data.stoppage === null || (data.stoppage as unknown as string) === '' ? null : Number(data.stoppage),
            description: data.description === '' ? null : data.description,
        }))
        .post(storeEvent(props.routeArgs).url, {
            preserveScroll: true,
            onSuccess: () => {
                eventForm.reset('player_id', 'assist_player_id', 'secondary_player_id', 'description');
            },
        });
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
            <div class="flex items-end gap-3">
                <div class="w-24">
                    <Label for="minute">Minute</Label>
                    <Input id="minute" v-model.number="minute" type="number" min="0" max="200" placeholder="—" />
                </div>
                <p class="pb-2 text-sm text-muted-foreground">Current: <span class="font-medium text-foreground">{{ currentStatusLabel }}</span></p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="action in statusActions"
                    :key="action.label"
                    type="button"
                    size="sm"
                    :variant="action.variant ?? 'default'"
                    :disabled="statusForm.processing"
                    @click="setStatus(action.status)"
                >
                    {{ action.label }}
                </Button>
            </div>
            <InputError :message="statusForm.errors.status" />
        </div>

        <hr class="border-border" />

        <!-- Event entry -->
        <form class="space-y-3" @submit.prevent="submitEvent">
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

            <Button type="submit" :disabled="eventForm.processing">Record event</Button>
        </form>
    </div>
</template>
