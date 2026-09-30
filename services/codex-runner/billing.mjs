export const usageFromJsonl = (text) => {
  let usage = null;
  for (const line of text.split(/\r?\n/)) {
    try {
      const event = JSON.parse(line);
      if (event.type === "turn.completed" && event.usage) usage = event.usage;
    } catch {}
  }
  if (!usage) throw new Error("Codex nie zwrócił danych zużycia; koszt wymaga ręcznego rozliczenia.");
  return usage;
};

export const costFromUsage = (usage, rates) => {
  const input = Number(usage?.input_tokens || 0);
  const cached = Number(usage?.cached_input_tokens || 0);
  const output = Number(usage?.output_tokens || 0);
  return Math.round(((Math.max(0, input - cached) * rates.input + cached * rates.cached + output * rates.output) / 1_000_000) * 100) / 100;
};

export async function settleUsage(task, updateTask, jsonl, rates) {
  const cost = costFromUsage(usageFromJsonl(jsonl), rates);
  const total = Math.round(((Number(task.costPln) || 0) + cost) * 100) / 100;
  await updateTask(task, { costPln: total });
  return total;
}
