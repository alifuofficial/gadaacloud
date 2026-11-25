import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import Kenat, { toGC } from 'kenat';
import { Button } from '@/components/ui/button';
import { ETHIOPIAN_MONTH_NAMES, isEthiopianCalendarEnabled } from '@/utils/helpers';
import { CalendarDays } from 'lucide-react';

interface EthiopianFiscalPresetsProps {
  onSelectRange: (fromDate: string, toDate: string) => void;
}

function toIso(year: number, month: number, day: number): string {
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function ecToIso(ethYear: number, ethMonth: number, ethDay: number): string {
  const g = toGC(ethYear, ethMonth, ethDay);
  return toIso(g.year, g.month, g.day);
}

/**
 * Shared Ethiopian MoF/MoR fiscal year (Hamle 1 - Sene 30) & quarterly date-range presets.
 * Renders only when EthiopianCalendar add-on is subscribed and enabled by the tenant.
 */
export function EthiopianFiscalPresets({ onSelectRange }: EthiopianFiscalPresetsProps) {
  const { t } = useTranslation();
  const enabled = isEthiopianCalendarEnabled();

  const presets = useMemo(() => {
    if (!enabled) return [];
    try {
      const todayEth = new Kenat().getEthiopian();
      const fy = todayEth.month >= 11 ? todayEth.year + 1 : todayEth.year;
      const pagumeDays = todayEth.year % 4 === 3 ? 6 : 5;
      const monthEndDay = todayEth.month === 13 ? pagumeDays : 30;
      const monthLabel = ETHIOPIAN_MONTH_NAMES.english[todayEth.month - 1] || 'Month';

      return [
        {
          key: 'fy',
          label: t('FY {{fy}} (Hamle–Sene)', { fy }),
          from: ecToIso(fy - 1, 11, 1),
          to: ecToIso(fy, 10, 30),
        },
        {
          key: 'q1',
          label: `Q1 ${fy} (Ham–Mes)`,
          from: ecToIso(fy - 1, 11, 1),
          to: ecToIso(fy, 1, 30),
        },
        {
          key: 'q2',
          label: `Q2 ${fy} (Tik–Tah)`,
          from: ecToIso(fy, 2, 1),
          to: ecToIso(fy, 4, 30),
        },
        {
          key: 'q3',
          label: `Q3 ${fy} (Tir–Meg)`,
          from: ecToIso(fy, 5, 1),
          to: ecToIso(fy, 7, 30),
        },
        {
          key: 'q4',
          label: `Q4 ${fy} (Mia–Sen)`,
          from: ecToIso(fy, 8, 1),
          to: ecToIso(fy, 10, 30),
        },
        {
          key: 'month',
          label: `${monthLabel} ${todayEth.year} E.C.`,
          from: ecToIso(todayEth.year, todayEth.month, 1),
          to: ecToIso(todayEth.year, todayEth.month, monthEndDay),
        },
      ];
    } catch {
      return [];
    }
  }, [enabled, t]);

  if (!enabled || presets.length === 0) return null;

  return (
    <div className="mt-3 pt-3 border-t border-gray-200 flex flex-wrap items-center gap-1.5">
      <span className="inline-flex items-center gap-1 text-xs font-medium text-amber-800 mr-1">
        <CalendarDays className="h-3.5 w-3.5 text-amber-600" />
        {t('Ethiopian Fiscal Presets')}:
      </span>
      {presets.map((preset) => (
        <Button
          key={preset.key}
          type="button"
          variant="outline"
          size="sm"
          className="h-7 px-2.5 text-xs bg-white hover:bg-amber-50 border-amber-200 text-amber-900"
          onClick={() => onSelectRange(preset.from, preset.to)}
        >
          {preset.label}
        </Button>
      ))}
    </div>
  );
}
