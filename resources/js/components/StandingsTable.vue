<script setup lang="ts">
interface StandingRow {
    team_id: number;
    team_name: string;
    team_acronym: string;
    played: number;
    won: number;
    drawn: number;
    lost: number;
    goals_for: number;
    goals_against: number;
    goal_difference: number;
    points: number;
    form: string;
}

defineProps<{
    rows: StandingRow[];
}>();

function formColor(letter: string): string {
    switch (letter) {
        case 'W':
            return 'bg-emerald-500 text-white';
        case 'L':
            return 'bg-rose-500 text-white';
        case 'D':
            return 'bg-amber-400 text-amber-950';
        default:
            return 'bg-muted text-muted-foreground';
    }
}
</script>

<template>
    <div class="overflow-hidden rounded-md border">
        <table class="w-full text-sm">
            <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                    <th class="px-3 py-2 text-right">#</th>
                    <th class="px-3 py-2 text-left">Team</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums sm:table-cell">Pld</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums md:table-cell">W</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums md:table-cell">D</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums md:table-cell">L</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums lg:table-cell">GF</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums lg:table-cell">GA</th>
                    <th class="px-2 py-2 text-right tabular-nums">GD</th>
                    <th class="px-3 py-2 text-right tabular-nums font-semibold">Pts</th>
                    <th class="hidden px-3 py-2 text-left lg:table-cell">Form</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr v-for="(row, index) in rows" :key="row.team_id" class="hover:bg-muted/30">
                    <td class="px-3 py-2 text-right text-muted-foreground tabular-nums">{{ index + 1 }}</td>
                    <td class="px-3 py-2">
                        <span class="mr-2 rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">
                            {{ row.team_acronym }}
                        </span>
                        <span>{{ row.team_name }}</span>
                    </td>
                    <td class="hidden px-2 py-2 text-right tabular-nums sm:table-cell">{{ row.played }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums md:table-cell">{{ row.won }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums md:table-cell">{{ row.drawn }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums md:table-cell">{{ row.lost }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums lg:table-cell">{{ row.goals_for }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums lg:table-cell">{{ row.goals_against }}</td>
                    <td class="px-2 py-2 text-right tabular-nums" :class="{ 'text-emerald-600 dark:text-emerald-400': row.goal_difference > 0, 'text-rose-600 dark:text-rose-400': row.goal_difference < 0 }">
                        {{ row.goal_difference > 0 ? '+' : '' }}{{ row.goal_difference }}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ row.points }}</td>
                    <td class="hidden px-3 py-2 lg:table-cell">
                        <div v-if="row.form" class="flex items-center gap-0.5">
                            <span
                                v-for="(letter, i) in row.form.split('')"
                                :key="i"
                                class="inline-flex h-4 w-4 items-center justify-center rounded-sm text-[10px] font-bold"
                                :class="formColor(letter)"
                            >
                                {{ letter }}
                            </span>
                        </div>
                        <span v-else class="text-xs text-muted-foreground">—</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
