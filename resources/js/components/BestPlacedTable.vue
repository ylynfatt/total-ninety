<script setup lang="ts">
interface BestPlacedRow {
    group_id: number;
    group_name: string;
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

const props = defineProps<{
    rows: BestPlacedRow[];
    qualifyCount: number;
}>();

function qualifies(index: number): boolean {
    return index < props.qualifyCount;
}

function isCutLine(index: number): boolean {
    return index === props.qualifyCount - 1 && index < props.rows.length - 1;
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border">
        <table class="w-full text-sm">
            <thead class="bg-primary text-xs uppercase tracking-wider text-primary-foreground">
                <tr>
                    <th class="px-3 py-2 text-right">#</th>
                    <th class="px-3 py-2 text-left">Team</th>
                    <th class="px-2 py-2 text-left">Group</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums sm:table-cell">Pld</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums md:table-cell">W</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums md:table-cell">D</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums md:table-cell">L</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums lg:table-cell">GF</th>
                    <th class="hidden px-2 py-2 text-right tabular-nums lg:table-cell">GA</th>
                    <th class="px-2 py-2 text-right tabular-nums">GD</th>
                    <th class="px-3 py-2 text-right tabular-nums font-semibold">Pts</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr
                    v-for="(row, index) in rows"
                    :key="row.team_id"
                    class="border-l-2 hover:bg-muted/30"
                    :class="[
                        qualifies(index) ? 'border-l-volt bg-volt/5' : 'border-l-transparent',
                        isCutLine(index) ? 'border-b-2 border-b-volt/60' : '',
                    ]"
                >
                    <td class="px-3 py-2 text-right font-display text-base tabular-nums" :class="qualifies(index) ? 'font-bold' : 'text-muted-foreground'">
                        {{ index + 1 }}
                    </td>
                    <td class="px-3 py-2">
                        <span class="mr-2 inline-block w-10 rounded bg-muted px-1 text-center font-display text-xs font-semibold uppercase text-muted-foreground">
                            {{ row.team_acronym }}
                        </span>
                        <span :class="{ 'font-semibold': qualifies(index) }">{{ row.team_name }}</span>
                    </td>
                    <td class="px-2 py-2 text-xs text-muted-foreground">{{ row.group_name }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums sm:table-cell">{{ row.played }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums md:table-cell">{{ row.won }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums md:table-cell">{{ row.drawn }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums md:table-cell">{{ row.lost }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums lg:table-cell">{{ row.goals_for }}</td>
                    <td class="hidden px-2 py-2 text-right tabular-nums lg:table-cell">{{ row.goals_against }}</td>
                    <td class="px-2 py-2 text-right tabular-nums" :class="{ 'text-emerald-600 dark:text-emerald-400': row.goal_difference > 0, 'text-rose-600 dark:text-rose-400': row.goal_difference < 0 }">
                        {{ row.goal_difference > 0 ? '+' : '' }}{{ row.goal_difference }}
                    </td>
                    <td class="px-3 py-2 text-right font-display text-base font-bold tabular-nums">{{ row.points }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
