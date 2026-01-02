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

export type AccountType = 'bank' | 'credit_card' | 'investment' | 'cash' | 'loan' | 'other';

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
