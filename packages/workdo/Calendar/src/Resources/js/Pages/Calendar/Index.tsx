import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import CalendarView from '@/components/calendar-view';
import { CalendarDays } from 'lucide-react';
import { formatDualDate, isEthiopianCalendarEnabled } from '@/utils/helpers';

interface CalendarEvent {
  id: number;
  title: string;
  start: string;
  end: string;
  description?: string;
  event_type: string;
  startDate: string;
  endDate: string;
  time: string;
  type: string;
  color: string;
  attendees: string[];
}

interface CalendarIndexProps {
  events: CalendarEvent[];
  filters: {
    calendar_type: string;
    module_filter: string;
  };
  availableFilters: Record<string, string>;
  hasGoogleCalendarConfig: boolean;
}

export default function CalendarIndex({ events, filters, availableFilters, hasGoogleCalendarConfig }: CalendarIndexProps) {
  const { t } = useTranslation();
  const [calendarType, setCalendarType] = useState(filters.calendar_type);
  const [moduleFilter, setModuleFilter] = useState(filters.module_filter);
  const ethEnabled = isEthiopianCalendarEnabled();

  const breadcrumbs = [
    { label: t('Calendar') }
  ];

  const handleFilterChange = (type: string, value: string) => {
    const params = new URLSearchParams(window.location.search);
    params.set(type, value);
    router.get(route('calendar.view.index'), Object.fromEntries(params), {
      preserveState: true,
      preserveScroll: true,
      onError: (errors) => {
        
      }
    });
  };

  return (
    <AuthenticatedLayout
      breadcrumbs={breadcrumbs}
      pageTitle={t('Manage Calendar')}
      pageActions={
        <div className="flex items-center gap-2">
          <Card className="p-3">
            <RadioGroup
              value={calendarType}
              onValueChange={(value) => {
                setCalendarType(value);
                handleFilterChange('calendar_type', value);
              }}
              className="flex items-center gap-4"
            >
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="local" id="local" />
                <Label htmlFor="local">{t('Calendar')}</Label>
              </div>
              {hasGoogleCalendarConfig && (
                <div className="flex items-center space-x-2">
                  <RadioGroupItem value="google" id="google" />
                  <Label htmlFor="google">{t('Google Calendar')}</Label>
                </div>
              )}
            </RadioGroup>
          </Card>
          <Select
            value={moduleFilter}
            onValueChange={(value) => {
              setModuleFilter(value);
              handleFilterChange('module_filter', value);
            }}
          >
            <SelectTrigger className="w-48">
              <SelectValue placeholder={t('Select Filter')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('All')}</SelectItem>
              {Object.entries(availableFilters).map(([key, label]) => (
                <SelectItem key={key} value={key}>{label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      }
    >
      <Head title={t('Calendar')} />

      {ethEnabled && (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50/70 dark:border-amber-800 dark:bg-amber-950/30 px-4 py-2.5">
          <div className="flex items-center gap-2 text-sm text-amber-900 dark:text-amber-100">
            <CalendarDays className="h-4 w-4 text-amber-600" />
            <span className="font-medium">{t("Today in Ge'ez")}:</span>
            <span>{formatDualDate(new Date())}</span>
          </div>
          <Link href={route('ethiopian-calendar.index')}>
            <Button variant="outline" size="sm" className="h-7 text-xs border-amber-300 bg-white hover:bg-amber-100 dark:bg-amber-900/40">
              {t("Open 13-Month Ge'ez Grid")}
            </Button>
          </Link>
        </div>
      )}

      <Card className="shadow-sm">
        <div className="w-full p-6">
          <CalendarView events={events} />
        </div>
      </Card>
    </AuthenticatedLayout>
  );
}
