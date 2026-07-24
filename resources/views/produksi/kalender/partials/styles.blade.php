<style>
    /* Theme-aware: follows the app's --card/--text/--muted/--border variables (light & dark). */
    .pk-wrap { color: var(--text); }
    .pk-mono { font-variant-numeric: tabular-nums; font-feature-settings: "tnum"; }
    .pk-muted { color: var(--muted); }

    .pk-kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin: 10px 0 14px; }
    .pk-kpi { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 10px 14px; border-left: 4px solid var(--muted); }
    .pk-kpi .n { font-size: 20px; font-weight: 700; color: var(--text); line-height: 1.2; }
    .pk-kpi .l { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }
    .pk-kpi.total { border-left-color: #2457c5; }
    .pk-kpi.antre { border-left-color: #8896a6; }
    .pk-kpi.proses { border-left-color: #e08a1e; }
    .pk-kpi.selesai { border-left-color: #3a9d63; }
    .pk-kpi.telat { border-left-color: #d24b3e; }

    .pk-toolbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 4px; }
    .pk-monthnav { display: flex; align-items: center; gap: 8px; }
    .pk-monthnav a { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: 1px solid var(--border); border-radius: 8px; background: var(--card); text-decoration: none; color: var(--text); font-weight: 700; }
    .pk-monthnav a:hover { border-color: #2457c5; color: #2457c5; }
    .pk-monthnav .lbl { font-weight: 700; min-width: 140px; text-align: center; color: var(--text); }

    .pk-tabs { display: inline-flex; border: 1px solid var(--border); border-radius: 9px; overflow: hidden; background: var(--card); }
    .pk-tabs button { padding: 8px 14px; border: 0; background: transparent; color: var(--muted); font-weight: 600; cursor: pointer; font-size: 13px; }
    .pk-tabs button.active { background: #2457c5; color: #fff; }

    .pk-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .pk-chip { padding: 5px 11px; border-radius: 20px; border: 1px solid var(--border); background: var(--card); cursor: pointer; font-size: 12px; font-weight: 600; color: var(--muted); user-select: none; }
    .pk-chip.active { background: #2457c5; border-color: #2457c5; color: #fff; }

    .pk-status { padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
    .pk-status.antre { background: #eceff3; color: #4a5666; } .pk-status.proses { background: #fdefdb; color: #8a5200; }
    .pk-status.selesai { background: #e3f2e9; color: #1f7a45; } .pk-status.telat { background: #fbe3e1; color: #b3261e; }

    .pk-panel { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 12px; }

    .pk-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
    .pk-grid .head { font-size: 11px; font-weight: 700; color: var(--muted); text-align: center; padding: 2px 0 6px; text-transform: uppercase; letter-spacing: .3px; }
    .pk-week { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; position: relative; margin-bottom: 6px; }
    .pk-cell { min-height: 66px; border: 1px solid var(--border); border-radius: 8px; padding: 4px 6px; background: var(--card); }
    .pk-cell.out { background: var(--surface); color: var(--muted); opacity: .6; }
    .pk-cell.today { border-color: #2457c5; box-shadow: inset 0 0 0 1px #2457c5; }
    .pk-cell .d { font-size: 11px; font-weight: 700; color: var(--muted); }
    .pk-bars { position: absolute; inset: 22px 0 4px; display: grid; grid-template-columns: repeat(7, 1fr); grid-auto-rows: 18px; gap: 3px; pointer-events: none; }
    .pk-bar { border-radius: 5px; font-size: 10px; font-weight: 700; padding: 1px 6px; color: #fff; cursor: pointer; pointer-events: auto; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; display: flex; align-items: center; }
    .pk-bar.antre { background: #7c8a9c; } .pk-bar.proses { background: #e08a1e; } .pk-bar.selesai { background: #3a9d63; } .pk-bar.telat { background: #d24b3e; }

    .pk-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .pk-table th, .pk-table td { padding: 9px 10px; border-bottom: 1px solid var(--table-border-soft); text-align: left; color: var(--text); }
    .pk-table th { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .3px; }
    .pk-table tbody tr:hover { background: var(--surface); }
    .pk-prog { height: 7px; border-radius: 4px; background: var(--surface); overflow: hidden; min-width: 90px; border: 1px solid var(--border); }
    .pk-prog > span { display: block; height: 100%; background: #2457c5; }

    .pk-tl-cell { border: 1px solid var(--table-border-soft); border-radius: 6px; min-height: 44px; padding: 3px; vertical-align: top; }
    .pk-tl-chip { display: block; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-bottom: 3px; color: #fff; cursor: pointer; }
    .pk-tl-chip.antre { background: #7c8a9c; } .pk-tl-chip.proses { background: #e08a1e; } .pk-tl-chip.selesai { background: #3a9d63; } .pk-tl-chip.telat { background: #d24b3e; }

    /* Drawer + modal: hidden by default (display:none) so nothing stray renders. */
    .pk-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 9998; display: none; }
    .pk-overlay.open { display: block; }
    .pk-drawer { position: fixed; top: 0; right: 0; height: 100%; width: min(560px, 100%); background: var(--card); color: var(--text); z-index: 9999; overflow-y: auto; box-shadow: -4px 0 24px rgba(0,0,0,.25); display: none; }
    .pk-drawer.open { display: block; }
    .pk-modal-box { position: fixed; inset: 24px; max-width: 960px; margin: auto; background: var(--card); color: var(--text); border-radius: 12px; overflow: auto; z-index: 9999; }

    .pk-wrap input, .pk-wrap select, .pk-wrap textarea { background: var(--input-bg); color: var(--text); border: 1px solid var(--border); border-radius: 7px; padding: 6px 9px; }
    .pk-wrap label { font-size: 12px; color: var(--muted); display: block; margin-bottom: 3px; }
    .pk-hidden { display: none !important; }
    @media (max-width: 720px) { .pk-kpis { grid-template-columns: repeat(2, 1fr); } }
</style>
