<div class="space-y-3">
    <p class="text-sm"><span class="font-medium">Asunto:</span> {{ $subject }}</p>
    <iframe title="Vista previa del correo" srcdoc="{{ $html }}" class="h-[32rem] w-full rounded-lg border border-gray-200 bg-white dark:border-white/10" sandbox></iframe>
</div>
