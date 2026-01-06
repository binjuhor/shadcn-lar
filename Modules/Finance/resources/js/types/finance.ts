export interface Currency {
  code: string;
  name: string;
  symbol: string;
  decimal_places: number;
  active: boolean;
  is_default: boolean;
  created_at?: string;
  updated_at?: string;
}

export type AccountType = 'bank' | 'credit_card' | 'investment' | 'cash' | 'loan' | 'e_wallet' | 'other';

export type RateSource = 'exchangerate_api' | 'open_exchange_rates' | 'vietcombank' | 'payoneer' | null;

export interface Account {
  id: number;
  user_id: number;
  name: string;
  account_type: AccountType;
  currency_code: string;
  rate_source?: RateSource;
  currency?: Currency;
  balance: number;
  initial_balance: number;
  current_balance: number;
  description?: string;
  icon?: string;
  color?: string;
  is_active: boolean;
  is_default_payment: boolean;
  exclude_from_total: boolean;
  created_at: string;
  updated_at: string;
}

export type TransactionType = 'income' | 'expense' | 'transfer';

export type CategoryType = 'income' | 'expense' | 'both';

export interface Category {
  id: number;
  user_id?: number;
  parent_id?: number;
  name: string;
  type: CategoryType;
  icon?: string;
  color?: string;
  is_active: boolean;
  is_passive: boolean;
  _lft?: number;
  _rgt?: number;
  children?: Category[];
  created_at: string;
  updated_at: string;
}

export interface Transaction {
  id: number;
  user_id: number;
  account_id: number;
  account?: Account;
  category_id?: number;
  category?: Category;
  type: TransactionType;
  amount: number;
  currency_code: string;
  currency?: Currency;
  description?: string;
  notes?: string;
  transaction_date: string;
  is_reconciled: boolean;
  transfer_account_id?: number;
  transfer_account?: Account;
  transfer_transaction_id?: number;
  created_at: string;
  updated_at: string;
}

export type BudgetPeriod = 'weekly' | 'monthly' | 'quarterly' | 'yearly' | 'custom';

export interface Budget {
  id: number;
  user_id: number;
  category_id?: number;
  category?: Category;
  name: string;
  amount: number;
  currency_code: string;
  currency?: Currency;
  spent: number;
  period_type: BudgetPeriod;
  start_date: string;
  end_date: string;
  is_active: boolean;
  rollover: boolean;
  created_at: string;
  updated_at: string;
}

export interface AccountSummary {
  total_balance: number;
  total_assets: number;
  total_liabilities: number;
  net_worth: number;
  currency_code: string;
  accounts_count: number;
}

export interface FinanceDashboardData {
  summary: AccountSummary;
  recentTransactions: Transaction[];
  budgets: Budget[];
  spendingTrend: SpendingTrendItem[];
}

export interface SpendingTrendItem {
  date: string;
  amount: number;
}

export interface TransactionFilters {
  account_id?: number;
  category_id?: number;
  type?: TransactionType;
  date_from?: string;
  date_to?: string;
  search?: string;
}

export interface BudgetWithProgress extends Budget {
  spent_percent: number;
  remaining: number;
  is_over_budget: boolean;
}

export interface PaginatedData<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ExchangeRate {
  id: number;
  base_currency: string;
  target_currency: string;
  rate: number;
  bid_rate?: number;
  ask_rate?: number;
  source: string;
  rate_date: string;
  baseCurrency?: Currency;
  targetCurrency?: Currency;
  created_at: string;
  updated_at: string;
}

export interface ExchangeRateFilters {
  base_currency?: string;
  target_currency?: string;
  source?: string;
}

// Report types
export type DateRangePreset = '30d' | '6m' | '12m' | 'ytd' | 'custom';

export interface ReportFilters {
  range: DateRangePreset;
  startDate: string;
  endDate: string;
}

export interface IncomeExpensePoint {
  period: string;
  income: number;
  expense: number;
}

export interface CategoryBreakdownItem {
  id: number;
  name: string;
  amount: number;
  percentage: number;
  color: string;
}

export interface AccountTypeBreakdown {
  type: AccountType;
  label: string;
  balance: number;
  count: number;
  color: string;
  isLiability: boolean;
}

export interface ReportSummary {
  totalIncome: number;
  totalExpense: number;
  netChange: number;
  previousPeriodChange: number;
}

export interface FinanceReportData {
  filters: ReportFilters;
  incomeExpenseTrend: IncomeExpensePoint[];
  categoryBreakdown: CategoryBreakdownItem[];
  accountDistribution: AccountTypeBreakdown[];
  summary: ReportSummary;
  currencyCode: string;
}

// Savings Goals
export type SavingsGoalStatus = 'active' | 'completed' | 'paused' | 'cancelled';

export type ContributionType = 'manual' | 'linked';

export interface SavingsGoal {
  id: number;
  user_id: number;
  target_account_id?: number;
  target_account?: Account;
  name: string;
  description?: string;
  icon?: string;
  color?: string;
  target_amount: number;
  current_amount: number;
  currency_code: string;
  currency?: Currency;
  target_date?: string;
  status: SavingsGoalStatus;
  is_active: boolean;
  completed_at?: string;
  progress_percent: number;
  remaining_amount: number;
  contributions?: SavingsContribution[];
  created_at: string;
  updated_at: string;
}

export interface SavingsContribution {
  id: number;
  savings_goal_id: number;
  transaction_id?: number;
  transaction?: Transaction;
  amount: number;
  currency_code: string;
  contribution_date: string;
  notes?: string;
  type: ContributionType;
  created_at: string;
  updated_at: string;
}

// Financial Planning
export type PlanStatus = 'draft' | 'active' | 'archived';

export type PlanItemRecurrence = 'one_time' | 'monthly' | 'quarterly' | 'yearly';

export type PlanItemType = 'income' | 'expense';

export interface PlanItem {
  id: number;
  name: string;
  type: PlanItemType;
  planned_amount: number;
  recurrence: PlanItemRecurrence;
  category_id?: number;
  category?: {
    id: number;
    name: string;
    color?: string;
  };
  notes?: string;
}

export interface PlanPeriod {
  id: number;
  year: number;
  planned_income: number;
  planned_expense: number;
  net_planned: number;
  notes?: string;
  items: PlanItem[];
}

export interface FinancialPlan {
  id: number;
  name: string;
  description?: string;
  start_year: number;
  end_year: number;
  year_span: number;
  currency_code: string;
  status: PlanStatus;
  total_planned_income: number;
  total_planned_expense: number;
  net_planned: number;
  periods?: PlanPeriod[];
  created_at: string;
  updated_at?: string;
}

export interface PlanComparison {
  year: number;
  planned_income: number;
  planned_expense: number;
  actual_income: number;
  actual_expense: number;
  income_variance: number;
  expense_variance: number;
  income_variance_percent: number;
  expense_variance_percent: number;
}

export interface PlanFormItem {
  [key: string]: string | number | undefined;
  id?: number;
  name: string;
  type: PlanItemType;
  planned_amount: number;
  recurrence: PlanItemRecurrence;
  category_id?: number;
  notes?: string;
}

export interface PlanFormPeriod {
  [key: string]: number | PlanFormItem[] | undefined;
  id?: number;
  year: number;
  items: PlanFormItem[];
}

export interface PlanFormData {
  [key: string]: string | number | PlanFormPeriod[] | undefined;
  name: string;
  description?: string;
  start_year: number;
  end_year: number;
  currency_code: string;
  status: PlanStatus;
  periods: PlanFormPeriod[];
}

// Smart Input
export interface ParsedTransaction {
  type: TransactionType;
  amount: number;
  description: string;
  suggested_category?: {
    id: number;
    name: string;
  };
  suggested_account?: {
    id: number;
    name: string;
  };
  transaction_date: string;
  confidence: number;
  raw_text?: string;
}

export interface SmartInputFormData {
  type: TransactionType;
  amount: number;
  description: string;
  account_id: number;
  category_id?: number;
  transaction_date: string;
  notes?: string;
}

// Cashflow Analysis Types
export interface CashflowMonthlyData {
  period: string;
  label: string;
  passiveIncome: number;
  activeIncome: number;
  totalIncome: number;
  expense: number;
  surplus: number;
  passiveCoverage: number;
}

export interface CashflowAverages {
  passiveIncome: number;
  expense: number;
  coverage: number;
}

export interface CashflowAnalysis {
  monthlyData: CashflowMonthlyData[];
  averages: CashflowAverages;
  financialFreedomProgress: number;
}

// Recurring Transactions
export type RecurringFrequency = 'daily' | 'weekly' | 'monthly' | 'yearly';

export interface RecurringTransaction {
  id: number;
  user_id: number;
  account_id: number;
  account?: Account;
  category_id?: number;
  category?: Category;
  name: string;
  description?: string;
  transaction_type: 'income' | 'expense';
  amount: number;
  currency_code: string;
  frequency: RecurringFrequency;
  day_of_week?: number;
  day_of_month?: number;
  month_of_year?: number;
  start_date: string;
  end_date?: string;
  next_run_date: string;
  last_run_date?: string;
  is_active: boolean;
  auto_create: boolean;
  monthly_amount: number;
  yearly_amount: number;
  created_at: string;
  updated_at: string;
}

export interface RecurringPreview {
  date: string;
  amount: number;
  type: 'income' | 'expense';
}

export interface MonthlyProjection {
  monthly_income: number;
  monthly_expense: number;
  monthly_passive_income: number;
  monthly_net: number;
  passive_coverage: number;
  currency_code: string;
}
