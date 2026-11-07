@props(['target', 'mode' => 'set', 'submit' => false, 'label' => 'Camera'])
{{-- Opens the phone/laptop camera and reads a barcode / QR code into the target field. Needs HTTPS. --}}
<button type="button" data-camera-scan="{{ $target }}" data-camera-mode="{{ $mode }}" @if ($submit) data-camera-submit="1" @endif
        {{ $attributes->merge(['class' => 'inline-flex h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-brand bg-white px-3 text-xs font-bold text-brand hover:bg-brand-tint']) }}>
    <i data-lucide="camera" class="h-4 w-4"></i><span class="hidden sm:inline">{{ $label }}</span>
</button>
