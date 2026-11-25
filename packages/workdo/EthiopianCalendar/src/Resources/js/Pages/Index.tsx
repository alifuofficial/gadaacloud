import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import Kenat, { MonthGrid, getBahireHasab, getHolidaysForYear, toEC, toGC } from 'kenat';
import { ETHIOPIAN_MONTH_NAMES, formatEthiopianLocalTime } from '@/utils/helpers';
import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  ArrowLeftRight,
  Sparkles,
  Landmark,
  Clock,
  CheckCircle2,
  Save,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface CycleItem {
  month: number;
  year: number;
  name: string;
  name_am: string;
  label: string;
  start_date: string;
  end_date: string;
}

interface Props {
  todayEth: { year: number; month: number; day: number; month_name?: string; month_name_am?: string };
  selectedYear: number;
  fiscalYear: {
    fiscal_year: number;
    label: string;
    start_date: string;
    end_date: string;
    quarters: Array<{ key: string; label: string; start_date: string; end_date: string }>;
  };
  monthlyCycles: CycleItem[];
  hasHrm: boolean;
  existingHolidays: Array<{ id: number; name: string; start_date: string | null }>;
  calendarSettings: {
    useEthiopianCalendar: string;
    ethiopianDateStyle: string;
    showEthiopianEra: string;
    useEthiopianTime: string;
  };
}

export default function EthiopianCalendarIndex({
  todayEth,
  selectedYear,
  fiscalYear,
  monthlyCycles = [],
  hasHrm,
  existingHolidays = [],
  calendarSettings,
}: Props) {
  const { t } = useTranslation();

  const [viewYear, setViewYear] = useState<number>(selectedYear || todayEth.year || 2019);
  const [viewMonth, setViewMonth] = useState<number>(todayEth.month || 1);
  const [gregInput, setGregInput] = useState<string>(() => new Date().toISOString().slice(0, 10));
  const [ethConvYear, setEthConvYear] = useState<number>(todayEth.year || 2019);
  const [ethConvMonth, setEthConvMonth] = useState<number>(todayEth.month || 1);
  const [ethConvDay, setEthConvDay] = useState<number>(todayEth.day || 1);

  const [prefs, setPrefs] = useState({
    useEthiopianCalendar: calendarSettings?.useEthiopianCalendar === 'on' ? 'on' : 'off',
    ethiopianDateStyle: calendarSettings?.ethiopianDateStyle || 'numeric',
    showEthiopianEra: calendarSettings?.showEthiopianEra === 'on' ? 'on' : 'off',
    useEthiopianTime: calendarSettings?.useEthiopianTime === 'on' ? 'on' : 'off',
  });
  const [savingPrefs, setSavingPrefs] = useState(false);
  const [syncingHolidays, setSyncingHolidays] = useState(false);

  const monthGrid = useMemo(() => {
    try {
      return MonthGrid.create({
        year: viewYear,
        month: viewMonth,
        weekStart: 0,
        weekdayLang: 'english',
      });
    } catch {
      return null;
    }
  }, [viewYear, viewMonth]);

  const yearHolidays = useMemo(() => {
    try {
      const list = getHolidaysForYear(viewYear, { lang: 'english', filter: 'public' }) || [];
      return list.map((h) => {
        const greg = h.gregorian || toGC(h.ethiopian.year, h.ethiopian.month, h.ethiopian.day);
        const iso = `${greg.year}-${String(greg.month).padStart(2, '0')}-${String(greg.day).padStart(2, '0')}`;
        return {
          ...h,
          iso,
          ethDisplay: `${ETHIOPIAN_MONTH_NAMES.english[h.ethiopian.month - 1]} ${h.ethiopian.day}, ${h.ethiopian.year} E.C.`,
        };
      });
    } catch {
      return [];
    }
  }, [viewYear]);

  const bahireHasab = useMemo(() => {
    try {
      return getBahireHasab(viewYear, { lang: 'english' });
    } catch {
      return null;
    }
  }, [viewYear]);

  const existingDateSet = useMemo(() => {
    return new Set((existingHolidays || []).map((h) => `${h.start_date}|${h.name}`));
  }, [existingHolidays]);

  const convertedFromGregorian = useMemo(() => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(gregInput);
    if (!match) return null;
    try {
      const ec = toEC(Number(match[1]), Number(match[2]), Number(match[3]));
      return {
        ...ec,
        monthName: ETHIOPIAN_MONTH_NAMES.english[ec.month - 1],
        monthAm: ETHIOPIAN_MONTH_NAMES.amharic[ec.month - 1],
      };
    } catch {
      return null;
    }
  }, [gregInput]);

  const convertedFromEthiopian = useMemo(() => {
    try {
      const maxDay = ethConvMonth === 13 ? (ethConvYear % 4 === 3 ? 6 : 5) : 30;
      const safeDay = Math.min(Math.max(1, ethConvDay), maxDay);
      const gc = toGC(ethConvYear, ethConvMonth, safeDay);
      return `${gc.year}-${String(gc.month).padStart(2, '0')}-${String(gc.day).padStart(2, '0')}`;
    } catch {
      return 'Invalid Date';
    }
  }, [ethConvYear, ethConvMonth, ethConvDay]);

  const stepMonth = (delta: number) => {
    let nextMonth = viewMonth + delta;
    let nextYear = viewYear;
    if (nextMonth < 1) {
      nextMonth = 13;
      nextYear -= 1;
    } else if (nextMonth > 13) {
      nextMonth = 1;
      nextYear += 1;
    }
    setViewMonth(nextMonth);
    setViewYear(nextYear);
  };

  const handleSavePrefs = () => {
    setSavingPrefs(true);
    router.post(route('ethiopian-calendar.preferences.update'), prefs, {
      preserveScroll: true,
      onFinish: () => setSavingPrefs(false),
    });
  };

  const handleSyncHolidays = () => {
    setSyncingHolidays(true);
    router.post(
      route('ethiopian-calendar.sync-holidays'),
      {
        ethiopian_year: viewYear,
        holidays: yearHolidays.map((h) => ({
          name: h.name || h.key,
          start_date: h.iso,
          description: h.description || `${h.ethDisplay}`,
        })),
      },
      {
        preserveScroll: true,
        onFinish: () => setSyncingHolidays(false),
      }
    );
  };

  const now = new Date();
  const isLeapEthYear = viewYear % 4 === 3;

  return (
    <AuthenticatedLayout
      breadcrumbs={[{ label: t('Ethiopian Calendar') }]}
      pageTitle={t('Ethiopian Calendar & Fiscal Workspace')}
      pageActions={
        <div className="flex items-center gap-2">
          <Badge variant="outline" className="border-amber-400 bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200 px-3 py-1">
            {ETHIOPIAN_MONTH_NAMES.amharic[(todayEth.month || 1) - 1]} {todayEth.day}, {todayEth.year} ዓ.ም.
          </Badge>
          {hasHrm && (
            <Button onClick={handleSyncHolidays} disabled={syncingHolidays} size="sm" className="gap-1.5">
              <RefreshCw className={cn('h-4 w-4', syncingHolidays && 'animate-spin')} />
              {syncingHolidays ? t('Syncing...') : t('Sync {{year}} Holidays to HRM', { year: viewYear })}
            </Button>
          )}
        </div>
      }
    >
      <Head title={t('Ethiopian Calendar')} />

      <div className="space-y-6">
        {/* Top KPI Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card>
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-1.5">
                <CalendarDays className="h-4 w-4 text-amber-600" />
                {t("Today (Ge'ez)")}
              </CardDescription>
              <CardTitle className="text-lg">
                {ETHIOPIAN_MONTH_NAMES.english[(todayEth.month || 1) - 1]} {todayEth.day}, {todayEth.year} E.C.
              </CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              {ETHIOPIAN_MONTH_NAMES.amharic[(todayEth.month || 1) - 1]} {todayEth.day} • GC {now.toISOString().slice(0, 10)}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-1.5">
                <Landmark className="h-4 w-4 text-emerald-600" />
                {t('Ethiopian Fiscal Year')}
              </CardDescription>
              <CardTitle className="text-lg">FY {fiscalYear.fiscal_year} E.C.</CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              {fiscalYear.start_date} {t('to')} {fiscalYear.end_date} (Hamle 1 – Sene 30)
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-1.5">
                <Sparkles className="h-4 w-4 text-indigo-600" />
                {t('Evangelist / Year Type')}
              </CardDescription>
              <CardTitle className="text-lg">
                {bahireHasab?.evangelist?.name || 'Ethiopian Year'} ({viewYear})
              </CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              {isLeapEthYear
                ? t('Leap Year — Pagume has 6 days')
                : t('Standard Year — Pagume has 5 days')}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-1.5">
                <Clock className="h-4 w-4 text-sky-600" />
                {t('Ethiopian Local Time')}
              </CardDescription>
              <CardTitle className="text-lg">{formatEthiopianLocalTime(now.getHours(), now.getMinutes())}</CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              {t('Workspace mode')}: {prefs.useEthiopianCalendar === 'on' ? t('Active') : t('Gregorian Default')}
            </CardContent>
          </Card>
        </div>

        <Tabs defaultValue="calendar" className="space-y-4">
          <TabsList>
            <TabsTrigger value="calendar">{t('13-Month Ge\'ez Grid')}</TabsTrigger>
            <TabsTrigger value="holidays">{t('Public Holidays ({{count}})', { count: yearHolidays.length })}</TabsTrigger>
            <TabsTrigger value="fiscal">{t('Fiscal Quarters & Payroll Cycles')}</TabsTrigger>
            <TabsTrigger value="converter">{t('Converter & Settings')}</TabsTrigger>
          </TabsList>

          {/* TAB 1: Interactive 13-Month Calendar Grid */}
          <TabsContent value="calendar">
            <Card>
              <CardHeader className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b">
                <div>
                  <CardTitle className="flex items-center gap-2">
                    <span>
                      {ETHIOPIAN_MONTH_NAMES.english[viewMonth - 1]} ({ETHIOPIAN_MONTH_NAMES.amharic[viewMonth - 1]}) {viewYear} E.C.
                    </span>
                    {viewMonth === 13 && (
                      <Badge variant="secondary">
                        {t('13th Month ({{days}} days)', { days: isLeapEthYear ? 6 : 5 })}
                      </Badge>
                    )}
                  </CardTitle>
                  <CardDescription>
                    {t('Dual Ge\'ez day numerals (top) and Gregorian equivalents (bottom)')}
                  </CardDescription>
                </div>

                <div className="flex items-center gap-2 flex-wrap">
                  <Button variant="outline" size="icon" onClick={() => stepMonth(-1)}>
                    <ChevronLeft className="h-4 w-4" />
                  </Button>
                  <Select value={String(viewMonth)} onValueChange={(v) => setViewMonth(Number(v))}>
                    <SelectTrigger className="w-[175px]">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {ETHIOPIAN_MONTH_NAMES.english.map((name, idx) => (
                        <SelectItem key={idx + 1} value={String(idx + 1)}>
                          {idx + 1}. {name} ({ETHIOPIAN_MONTH_NAMES.amharic[idx]})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Input
                    type="number"
                    min={1900}
                    max={2200}
                    value={viewYear}
                    onChange={(e) => setViewYear(Number(e.target.value) || todayEth.year)}
                    className="w-[95px]"
                  />
                  <Button variant="outline" size="icon" onClick={() => stepMonth(1)}>
                    <ChevronRight className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      setViewYear(todayEth.year);
                      setViewMonth(todayEth.month);
                    }}
                  >
                    {t('Today')}
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-4">
                {monthGrid && (
                  <div className="grid grid-cols-7 gap-2">
                    {monthGrid.headers.map((h, i) => (
                      <div key={i} className="text-center text-xs font-semibold text-muted-foreground py-2 border-b">
                        {h}
                      </div>
                    ))}
                    {monthGrid.days.map((cell, idx) => {
                      if (!cell) {
                        return <div key={`blank-${idx}`} className="min-h-[82px] rounded-md bg-muted/20" />;
                      }

                      const gregIso = `${cell.gregorian.year}-${String(cell.gregorian.month).padStart(2, '0')}-${String(cell.gregorian.day).padStart(2, '0')}`;
                      const dayHolidays = cell.holidays || [];

                      return (
                        <div
                          key={`${cell.ethiopian.year}-${cell.ethiopian.month}-${cell.ethiopian.day}`}
                          className={cn(
                            'min-h-[82px] rounded-lg border p-2 flex flex-col justify-between transition-colors',
                            cell.isToday
                              ? 'border-amber-500 bg-amber-50/70 dark:bg-amber-950/30'
                              : dayHolidays.length > 0
                              ? 'border-emerald-300 bg-emerald-50/40 dark:bg-emerald-950/20'
                              : 'border-border bg-card'
                          )}
                        >
                          <div className="flex items-start justify-between">
                            <span className={cn('text-base font-bold', cell.isToday && 'text-amber-700 dark:text-amber-300')}>
                              {cell.ethiopian.day}
                            </span>
                            <span className="text-[11px] text-muted-foreground font-mono">
                              {cell.gregorian.month}/{cell.gregorian.day}
                            </span>
                          </div>

                          <div className="space-y-1">
                            {dayHolidays.slice(0, 2).map((h, hIdx) => (
                              <div
                                key={hIdx}
                                className="text-[10px] leading-tight px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 truncate"
                                title={h.name || h.key}
                              >
                                {h.name || h.key}
                              </div>
                            ))}
                            <div className="text-[10px] text-muted-foreground truncate">{gregIso}</div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* TAB 2: National & Movable Holidays (Bahire Hasab) */}
          <TabsContent value="holidays">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between border-b">
                <div>
                  <CardTitle>{t('Ethiopian Public Holidays — {{year}} E.C.', { year: viewYear })}</CardTitle>
                  <CardDescription>
                    {t('Computed dynamically via Kenat Bahire Hasab (including movable Siklet, Fasika & Islamic observances)')}
                  </CardDescription>
                </div>
                {hasHrm && (
                  <Button onClick={handleSyncHolidays} disabled={syncingHolidays} size="sm" className="gap-1.5">
                    <RefreshCw className={cn('h-4 w-4', syncingHolidays && 'animate-spin')} />
                    {t('Sync All to HRM')}
                  </Button>
                )}
              </CardHeader>
              <CardContent className="p-0 overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 border-b">
                    <tr>
                      <th className="text-left p-3 font-medium">{t('Holiday')}</th>
                      <th className="text-left p-3 font-medium">{t('Ethiopian Date')}</th>
                      <th className="text-left p-3 font-medium">{t('Gregorian Date')}</th>
                      <th className="text-left p-3 font-medium">{t('Type')}</th>
                      {hasHrm && <th className="text-left p-3 font-medium">{t('HRM Status')}</th>}
                    </tr>
                  </thead>
                  <tbody>
                    {yearHolidays.map((h) => {
                      const synced = existingDateSet.has(`${h.iso}|${h.name || h.key}`);
                      return (
                        <tr key={h.key} className="border-b hover:bg-muted/20">
                          <td className="p-3 font-medium">{h.name || h.key}</td>
                          <td className="p-3">{h.ethDisplay}</td>
                          <td className="p-3 font-mono text-xs">{h.iso}</td>
                          <td className="p-3">
                            <Badge variant={h.movable ? 'secondary' : 'outline'}>
                              {h.movable ? t('Movable') : t('Fixed')}
                            </Badge>
                          </td>
                          {hasHrm && (
                            <td className="p-3">
                              {synced ? (
                                <span className="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                                  <CheckCircle2 className="h-3.5 w-3.5" /> {t('Synced')}
                                </span>
                              ) : (
                                <span className="text-xs text-muted-foreground">{t('Not yet in HRM')}</span>
                              )}
                            </td>
                          )}
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </CardContent>
            </Card>
          </TabsContent>

          {/* TAB 3: Fiscal Quarters & Monthly Payroll Cycles */}
          <TabsContent value="fiscal">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <Card>
                <CardHeader>
                  <CardTitle>{fiscalYear.label}</CardTitle>
                  <CardDescription>{t('Ethiopian MoF / MoR statutory budget & tax quarters')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                  {(fiscalYear.quarters || []).map((q) => (
                    <div key={q.key} className="flex items-center justify-between p-3 rounded-lg border bg-muted/20">
                      <div>
                        <p className="font-medium text-sm">{q.label}</p>
                        <p className="text-xs text-muted-foreground font-mono">
                          {q.start_date} — {q.end_date}
                        </p>
                      </div>
                      <Badge variant="outline" className="uppercase">{q.key}</Badge>
                    </div>
                  ))}
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>{t('Ethiopian Payroll & VAT Monthly Cycles ({{year}} E.C.)', { year: selectedYear })}</CardTitle>
                  <CardDescription>{t('Standard 30-day statutory periods (Nehase includes Pagume)')}</CardDescription>
                </CardHeader>
                <CardContent className="p-0 max-h-[420px] overflow-y-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-muted/50 border-b sticky top-0">
                      <tr>
                        <th className="text-left p-2.5 font-medium">{t('Month')}</th>
                        <th className="text-left p-2.5 font-medium">{t('Amharic')}</th>
                        <th className="text-left p-2.5 font-medium">{t('Gregorian Window')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(monthlyCycles || []).map((c) => (
                        <tr key={c.month} className="border-b">
                          <td className="p-2.5 font-medium">{c.label}</td>
                          <td className="p-2.5">{c.name_am}</td>
                          <td className="p-2.5 font-mono text-xs">{c.start_date} → {c.end_date}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          {/* TAB 4: Bidirectional Converter & Display Preferences */}
          <TabsContent value="converter">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <ArrowLeftRight className="h-4 w-4" />
                    {t('Instant Bidirectional Date Converter')}
                  </CardTitle>
                  <CardDescription>{t('Convert any Gregorian or Ethiopian date accurately across leap years')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                  <div className="p-4 rounded-lg border space-y-3">
                    <Label className="font-semibold">{t('Gregorian → Ethiopian')}</Label>
                    <Input type="date" value={gregInput} onChange={(e) => setGregInput(e.target.value)} />
                    {convertedFromGregorian && (
                      <div className="text-sm font-medium text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/30 p-2.5 rounded">
                        {convertedFromGregorian.monthName} {convertedFromGregorian.day}, {convertedFromGregorian.year} E.C.
                        <span className="ml-2 text-muted-foreground">
                          ({convertedFromGregorian.monthAm} {convertedFromGregorian.day}, {convertedFromGregorian.year} ዓ.ም.)
                        </span>
                      </div>
                    )}
                  </div>

                  <div className="p-4 rounded-lg border space-y-3">
                    <Label className="font-semibold">{t('Ethiopian → Gregorian')}</Label>
                    <div className="grid grid-cols-3 gap-2">
                      <Select value={String(ethConvMonth)} onValueChange={(v) => setEthConvMonth(Number(v))}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          {ETHIOPIAN_MONTH_NAMES.english.map((m, idx) => (
                            <SelectItem key={idx + 1} value={String(idx + 1)}>
                              {m}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <Input
                        type="number"
                        min={1}
                        max={ethConvMonth === 13 ? 6 : 30}
                        value={ethConvDay}
                        onChange={(e) => setEthConvDay(Number(e.target.value) || 1)}
                      />
                      <Input
                        type="number"
                        min={1900}
                        max={2200}
                        value={ethConvYear}
                        onChange={(e) => setEthConvYear(Number(e.target.value) || 2019)}
                      />
                    </div>
                    <div className="text-sm font-mono font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/30 p-2.5 rounded">
                      Gregorian ISO: {convertedFromEthiopian}
                    </div>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>{t('Workspace Calendar Preferences')}</CardTitle>
                  <CardDescription>{t('Control how Ethiopian dates and time appear across this tenant')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div>
                      <Label>{t('Enable Ethiopian Calendar')}</Label>
                      <p className="text-xs text-muted-foreground">{t('Use Ge\'ez calendar across tables, pickers & PDFs')}</p>
                    </div>
                    <Switch
                      checked={prefs.useEthiopianCalendar === 'on'}
                      onCheckedChange={(c) => setPrefs((p) => ({ ...p, useEthiopianCalendar: c ? 'on' : 'off' }))}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label>{t('Ethiopian Date Style')}</Label>
                    <Select
                      value={prefs.ethiopianDateStyle}
                      onValueChange={(v) => setPrefs((p) => ({ ...p, ethiopianDateStyle: v }))}
                    >
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="numeric">{t('Numeric (2019-03-16)')}</SelectItem>
                        <SelectItem value="month_name">{t('English Month (Hidar 16, 2019)')}</SelectItem>
                        <SelectItem value="amharic">{t("Amharic Ge'ez (ኅዳር 16, 2019)")}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div>
                      <Label>{t('Show Era Indicator (E.C. / ዓ.ም.)')}</Label>
                    </div>
                    <Switch
                      checked={prefs.showEthiopianEra === 'on'}
                      onCheckedChange={(c) => setPrefs((p) => ({ ...p, showEthiopianEra: c ? 'on' : 'off' }))}
                    />
                  </div>

                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div>
                      <Label>{t('Ethiopian 12-Hour Local Clock')}</Label>
                    </div>
                    <Switch
                      checked={prefs.useEthiopianTime === 'on'}
                      onCheckedChange={(c) => setPrefs((p) => ({ ...p, useEthiopianTime: c ? 'on' : 'off' }))}
                    />
                  </div>

                  <Button onClick={handleSavePrefs} disabled={savingPrefs} className="w-full gap-2">
                    <Save className="h-4 w-4" />
                    {savingPrefs ? t('Saving...') : t('Save Calendar Preferences')}
                  </Button>
                </CardContent>
              </Card>
            </div>
          </TabsContent>
        </Tabs>
      </div>
    </AuthenticatedLayout>
  );
}
