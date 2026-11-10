@if (($fontFiles ?? []) !== [])
@font-face { font-family: "Times New Roman"; font-weight: normal; font-style: normal; src: url("{{ $fontFiles['times'] }}") format("truetype"); }
@font-face { font-family: "Times New Roman"; font-weight: bold; font-style: normal; src: url("{{ $fontFiles['times-bold'] }}") format("truetype"); }
@font-face { font-family: "Times New Roman"; font-weight: normal; font-style: italic; src: url("{{ $fontFiles['times-italic'] }}") format("truetype"); }
@font-face { font-family: "Times New Roman"; font-weight: bold; font-style: italic; src: url("{{ $fontFiles['times-bold-italic'] }}") format("truetype"); }
@font-face { font-family: Arial; font-weight: normal; font-style: normal; src: url("{{ $fontFiles['arial'] }}") format("truetype"); }
@font-face { font-family: Arial; font-weight: bold; font-style: normal; src: url("{{ $fontFiles['arial-bold'] }}") format("truetype"); }
@font-face { font-family: "Arial Black"; font-weight: 900; font-style: normal; src: url("{{ $fontFiles['arial-black'] }}") format("truetype"); }
@endif
