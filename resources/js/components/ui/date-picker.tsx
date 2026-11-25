"use client"

import * as React from "react"
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, ArrowLeftRight } from "lucide-react"
import ReactDatePicker from "react-datepicker"
import "react-datepicker/dist/react-datepicker.css"
import { useTranslation } from "react-i18next"
import Kenat, { MonthGrid } from "kenat"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import {
  ETHIOPIAN_MONTH_NAMES,
  formatDate,
  formatEthiopianParts,
  getCompanySetting,
  isEthiopianCalendarEnabled,
  parseDateSafely,
} from "@/utils/helpers"

interface DatePickerProps {
  value?: string
  onChange: (value: string) => void
  placeholder?: string
  className?: string
  id?: string
  required?: boolean
  maxDate?: Date
  minDate?: Date
  showYearDropdown?: boolean
  showMonthDropdown?: boolean
  style?: React.CSSProperties
}

function toIsoDate(year: number, month: number, day: number): string {
  return `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`
}

function isSameCalendarDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  )
}

export function DatePicker({
  value,
  onChange,
  placeholder,
  className,
  id,
  required,
  maxDate,
  minDate,
  showYearDropdown = true,
  showMonthDropdown = true,
  style,
}: DatePickerProps) {
  const { t } = useTranslation()
  const [open, setOpen] = React.useState(false)

  const ethEnabled = isEthiopianCalendarEnabled()
  const ethStyle = getCompanySetting("ethiopianDateStyle") || "numeric"
  const weekStart = Number(getCompanySetting("calendarStartDay") || "0") || 0
  const [pickerMode, setPickerMode] = React.useState<"ethiopian" | "gregorian">(
    ethEnabled ? "ethiopian" : "gregorian"
  )

  React.useEffect(() => {
    setPickerMode(ethEnabled ? "ethiopian" : "gregorian")
  }, [ethEnabled])

  const selectedDate = React.useMemo(() => parseDateSafely(value), [value])

  const initialEth = React.useMemo(() => {
    try {
      const ref = selectedDate || new Date()
      return new Kenat(ref).getEthiopian()
    } catch {
      return { year: 2019, month: 1, day: 1 }
    }
  }, [selectedDate])

  const [viewEthYear, setViewEthYear] = React.useState<number>(initialEth.year)
  const [viewEthMonth, setViewEthMonth] = React.useState<number>(initialEth.month)

  React.useEffect(() => {
    if (open && selectedDate) {
      try {
        const eth = new Kenat(selectedDate).getEthiopian()
        setViewEthYear(eth.year)
        setViewEthMonth(eth.month)
      } catch {
        // ignore invalid conversion
      }
    }
  }, [open, selectedDate])

  const formatTriggerValue = (date: Date | null): string => {
    if (!date) return ""
    if (ethEnabled) {
      const formatted = formatDate(date)
      if (formatted) return formatted
    }
    const options: Intl.DateTimeFormatOptions = {
      year: "numeric",
      month: "short",
      day: "numeric",
    }
    return date.toLocaleDateString("en-US", options)
  }

  const handleGregorianChange = (date: Date | null) => {
    if (date) {
      onChange(toIsoDate(date.getFullYear(), date.getMonth() + 1, date.getDate()))
      setOpen(false)
    } else {
      onChange("")
    }
  }

  const handleSelectEthiopianDay = (gregorian: { year: number; month: number; day: number }) => {
    onChange(toIsoDate(gregorian.year, gregorian.month, gregorian.day))
    setOpen(false)
  }

  const stepEthMonth = (delta: number) => {
    let nextMonth = viewEthMonth + delta
    let nextYear = viewEthYear
    if (nextMonth < 1) {
      nextMonth = 13
      nextYear -= 1
    } else if (nextMonth > 13) {
      nextMonth = 1
      nextYear += 1
    }
    setViewEthMonth(nextMonth)
    setViewEthYear(nextYear)
  }

  const isDayDisabled = (greg: { year: number; month: number; day: number }): boolean => {
    const candidate = new Date(greg.year, greg.month - 1, greg.day, 12, 0, 0)
    if (minDate) {
      const minFloor = new Date(minDate.getFullYear(), minDate.getMonth(), minDate.getDate(), 0, 0, 0)
      if (candidate < minFloor) return true
    }
    if (maxDate) {
      const maxCeil = new Date(maxDate.getFullYear(), maxDate.getMonth(), maxDate.getDate(), 23, 59, 59)
      if (candidate > maxCeil) return true
    }
    return false
  }

  const monthGrid = React.useMemo(() => {
    if (!ethEnabled || pickerMode !== "ethiopian") return null
    try {
      return MonthGrid.create({
        year: viewEthYear,
        month: viewEthMonth,
        weekStart,
        weekdayLang: ethStyle === "amharic" ? "amharic" : "english",
      })
    } catch {
      return null
    }
  }, [ethEnabled, pickerMode, viewEthYear, viewEthMonth, weekStart, ethStyle])

  const yearOptions = React.useMemo(() => {
    const currentEthYear = new Kenat().getEthiopian().year
    const startYear = Math.min(currentEthYear, viewEthYear) - 80
    const endYear = Math.max(currentEthYear, viewEthYear) + 30
    const list: number[] = []
    for (let y = endYear; y >= startYear; y--) {
      list.push(y)
    }
    return list
  }, [viewEthYear])

  return (
    <div className={cn("w-full", className)}>
      {id && <input id={id} type="hidden" value={value || ""} required={required} />}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            className={cn(
              "w-full justify-start text-left font-normal h-10",
              !value && "text-muted-foreground"
            )}
            style={style}
          >
            <CalendarIcon className="mr-2 h-4 w-4 shrink-0" />
            <span className="truncate">
              {value && selectedDate ? formatTriggerValue(selectedDate) : placeholder || t("Select date")}
            </span>
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-auto p-0" align="start">
          {ethEnabled && pickerMode === "ethiopian" && monthGrid ? (
            <div className="w-[300px] p-3 bg-background text-foreground">
              <div className="flex items-center justify-between gap-1 pb-2 border-b border-border">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-7 w-7"
                  onClick={() => stepEthMonth(-1)}
                  title={t("Previous Month")}
                >
                  <ChevronLeft className="h-4 w-4" />
                </Button>

                <div className="flex items-center gap-1.5 flex-1 justify-center">
                  <select
                    aria-label={t("Ethiopian Month")}
                    value={viewEthMonth}
                    onChange={(e) => setViewEthMonth(Number(e.target.value))}
                    className="h-7 rounded border border-border bg-background px-1.5 text-xs font-medium focus:outline-none focus:ring-1 focus:ring-ring"
                  >
                    {ETHIOPIAN_MONTH_NAMES.english.map((name, idx) => {
                      const monthNum = idx + 1
                      const label =
                        ethStyle === "amharic"
                          ? `${ETHIOPIAN_MONTH_NAMES.amharic[idx]} (${name})`
                          : name
                      return (
                        <option key={monthNum} value={monthNum}>
                          {label}
                        </option>
                      )
                    })}
                  </select>

                  <select
                    aria-label={t("Ethiopian Year")}
                    value={viewEthYear}
                    onChange={(e) => setViewEthYear(Number(e.target.value))}
                    className="h-7 rounded border border-border bg-background px-1.5 text-xs font-medium focus:outline-none focus:ring-1 focus:ring-ring"
                  >
                    {yearOptions.map((y) => (
                      <option key={y} value={y}>
                        {y} E.C.
                      </option>
                    ))}
                  </select>
                </div>

                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-7 w-7"
                  onClick={() => stepEthMonth(1)}
                  title={t("Next Month")}
                >
                  <ChevronRight className="h-4 w-4" />
                </Button>
              </div>

              <div className="grid grid-cols-7 gap-1 pt-2 pb-1 text-center">
                {monthGrid.headers.map((header, i) => (
                  <div key={i} className="text-[11px] font-medium text-muted-foreground py-1">
                    {String(header).slice(0, 2)}
                  </div>
                ))}
              </div>

              <div className="grid grid-cols-7 gap-1 text-center">
                {monthGrid.days.map((cell, idx) => {
                  if (!cell) {
                    return <div key={`empty-${idx}`} className="h-8 w-8" />
                  }

                  const cellDate = new Date(
                    cell.gregorian.year,
                    cell.gregorian.month - 1,
                    cell.gregorian.day,
                    12,
                    0,
                    0
                  )
                  const isSelected = selectedDate ? isSameCalendarDay(cellDate, selectedDate) : false
                  const disabled = isDayDisabled(cell.gregorian)
                  const hasHolidays = Array.isArray(cell.holidays) && cell.holidays.length > 0

                  return (
                    <button
                      key={`${cell.ethiopian.year}-${cell.ethiopian.month}-${cell.ethiopian.day}`}
                      type="button"
                      disabled={disabled}
                      onClick={() => handleSelectEthiopianDay(cell.gregorian)}
                      title={
                        hasHolidays
                          ? cell.holidays.map((h) => h.name || h.key).join(", ")
                          : `GC: ${toIsoDate(cell.gregorian.year, cell.gregorian.month, cell.gregorian.day)}`
                      }
                      className={cn(
                        "relative h-8 w-8 mx-auto flex flex-col items-center justify-center rounded-md text-xs transition-colors",
                        isSelected
                          ? "bg-primary text-primary-foreground font-semibold"
                          : cell.isToday
                          ? "border border-amber-500 text-amber-700 dark:text-amber-300 font-semibold"
                          : "hover:bg-accent hover:text-accent-foreground",
                        disabled && "opacity-40 cursor-not-allowed hover:bg-transparent"
                      )}
                    >
                      <span>{cell.ethiopian.day}</span>
                      {hasHolidays && (
                        <span
                          className={cn(
                            "absolute bottom-0.5 h-1 w-1 rounded-full",
                            isSelected ? "bg-primary-foreground" : "bg-amber-500"
                          )}
                        />
                      )}
                    </button>
                  )
                })}
              </div>

              <div className="mt-2 pt-2 border-t border-border flex items-center justify-between text-[11px] text-muted-foreground">
                <span className="truncate">
                  {selectedDate
                    ? `GC: ${toIsoDate(
                        selectedDate.getFullYear(),
                        selectedDate.getMonth() + 1,
                        selectedDate.getDate()
                      )}`
                    : formatEthiopianParts(
                        new Kenat().getEthiopian(),
                        "Y-m-d",
                        "month_name",
                        true
                      )}
                </span>
                <div className="flex items-center gap-1 shrink-0">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-6 px-1.5 text-[11px]"
                    onClick={() => {
                      const today = new Date()
                      handleSelectEthiopianDay({
                        year: today.getFullYear(),
                        month: today.getMonth() + 1,
                        day: today.getDate(),
                      })
                    }}
                  >
                    {t("Today")}
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-6 px-1.5 text-[11px] gap-1"
                    onClick={() => setPickerMode("gregorian")}
                    title={t("Switch to Gregorian Picker")}
                  >
                    <ArrowLeftRight className="h-3 w-3" />
                    GC
                  </Button>
                </div>
              </div>
            </div>
          ) : (
            <div className="date-picker-wrapper">
              {ethEnabled && (
                <div className="flex items-center justify-between px-3 py-1.5 border-b border-border bg-muted/30 text-[11px]">
                  <span className="text-muted-foreground">{t("Gregorian View")}</span>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-6 px-1.5 text-[11px] gap-1"
                    onClick={() => setPickerMode("ethiopian")}
                  >
                    <ArrowLeftRight className="h-3 w-3" />
                    {t("Ethiopian View")}
                  </Button>
                </div>
              )}
              <ReactDatePicker
                selected={selectedDate}
                onChange={handleGregorianChange}
                inline
                showPopperArrow={false}
                maxDate={maxDate}
                minDate={minDate}
                showYearDropdown={showYearDropdown}
                showMonthDropdown={showMonthDropdown}
                dropdownMode="select"
                yearDropdownItemNumber={100}
              />
            </div>
          )}
        </PopoverContent>
      </Popover>

      <style>{`
        .date-picker-wrapper .react-datepicker {
          font-family: inherit;
          border: none;
          background: hsl(var(--background));
          color: hsl(var(--foreground));
        }
        .date-picker-wrapper .react-datepicker__header {
          background: hsl(var(--background));
          border-bottom: 1px solid hsl(var(--border));
          border-radius: 0;
        }
        .date-picker-wrapper .react-datepicker__current-month,
        .date-picker-wrapper .react-datepicker__day-name {
          color: hsl(var(--foreground));
          font-weight: 500;
        }
        .date-picker-wrapper .react-datepicker__day {
          color: hsl(var(--foreground));
          border-radius: 6px;
        }
        .date-picker-wrapper .react-datepicker__day:hover {
          background: hsl(var(--accent));
          color: hsl(var(--accent-foreground));
        }
        .date-picker-wrapper .react-datepicker__day--selected {
          background: hsl(var(--primary));
          color: hsl(var(--primary-foreground));
        }
        .date-picker-wrapper .react-datepicker__navigation {
          border: none;
          border-radius: 6px;
        }
        .date-picker-wrapper .react-datepicker__navigation:hover {
          background: hsl(var(--accent));
        }
        .date-picker-wrapper .react-datepicker__navigation-icon::before {
          border-color: hsl(var(--foreground));
        }
        .date-picker-wrapper .react-datepicker__day--outside-month {
          color: hsl(var(--muted-foreground));
        }
        .date-picker-wrapper .react-datepicker__day--disabled {
          color: hsl(var(--muted-foreground));
          opacity: 0.5;
        }
        .date-picker-wrapper .react-datepicker__month-container {
          background: hsl(var(--background));
        }
        .date-picker-wrapper .react-datepicker__header__dropdown {
          display: flex;
          gap: 8px;
          justify-content: center;
          padding: 8px 0;
        }
        .date-picker-wrapper .react-datepicker__month-dropdown-container,
        .date-picker-wrapper .react-datepicker__year-dropdown-container {
          margin: 0;
        }
        .date-picker-wrapper .react-datepicker__year-select,
        .date-picker-wrapper .react-datepicker__month-select {
          background: hsl(var(--background));
          color: hsl(var(--foreground));
          border: 1px solid hsl(var(--border));
          border-radius: 6px;
          padding: 6px 32px 6px 12px;
          font-size: 13px;
          font-weight: 500;
          cursor: pointer;
          outline: none;
          appearance: none;
          background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
          background-repeat: no-repeat;
          background-position: right 8px center;
          background-size: 12px;
          min-width: 80px;
        }
        .date-picker-wrapper .react-datepicker__month-select {
          min-width: 100px;
        }
        .date-picker-wrapper .react-datepicker__year-select:hover,
        .date-picker-wrapper .react-datepicker__month-select:hover {
          background-color: hsl(var(--accent));
          border-color: hsl(var(--border));
        }
        .date-picker-wrapper .react-datepicker__year-select:focus,
        .date-picker-wrapper .react-datepicker__month-select:focus {
          border-color: hsl(var(--ring));
          box-shadow: 0 0 0 2px hsl(var(--ring) / 0.2);
        }
      `}</style>
    </div>
  )
}
