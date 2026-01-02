import { useState } from 'react'
import { Link, router, useForm } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  ArrowLeft,
  Target,
  Plus,
  Minus,
  Trash2,
  Calendar,
  CheckCircle,
  Pause,
  Link as LinkIcon,
} from 'lucide-react'
import type { SavingsGoal, SavingsContribution, Transaction } from '@modules/Finance/types/finance'

interface Props {
  goal: SavingsGoal
  availableTransactions: Transaction[]
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('vi-VN', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

export default function ShowSavingsGoal({ goal, availableTransactions }: Props) {
  const [showContributeDialog, setShowContributeDialog] = useState(false)
  const [showWithdrawDialog, setShowWithdrawDialog] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [selectedContribution, setSelectedContribution] = useState<SavingsContribution | null>(null)

  const contributeForm = useForm({
    amount: '',
    contribution_date: new Date().toISOString().split('T')[0],
    notes: '',
  })

  const withdrawForm = useForm({
    amount: '',
    contribution_date: new Date().toISOString().split('T')[0],
    notes: '',
  })

  const handleContribute = (e: React.FormEvent) => {
    e.preventDefault()
    contributeForm.post(route('dashboard.finance.savings-goals.contribute', goal.id), {
      onSuccess: () => {
        contributeForm.reset()
        setShowContributeDialog(false)
      },
    })
  }

  const handleWithdraw = (e: React.FormEvent) => {
    e.preventDefault()
    withdrawForm.post(route('dashboard.finance.savings-goals.withdraw', goal.id), {
      onSuccess: () => {
        withdrawForm.reset()
        setShowWithdrawDialog(false)
      },
    })
  }

  const handleDeleteContribution = () => {
    if (selectedContribution) {
      router.delete(
        route('dashboard.finance.savings-goals.unlink-contribution', [goal.id, selectedContribution.id]),
        {
          onSuccess: () => {
            setShowDeleteDialog(false)
            setSelectedContribution(null)
          },
        }
      )
    }
  }

  const progress = Math.min(goal.progress_percent, 100)
  const isCompleted = goal.status === 'completed'
  const isPaused = goal.status === 'paused'

  return (
    <AuthenticatedLayout title={goal.name}>
      <Main>
        <div className="mb-4">
          <Link
            href={route('dashboard.finance.savings-goals.index')}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Savings Goals
          </Link>
        </div>

        <div className="grid gap-6 lg:grid-cols-3">
          {/* Main Goal Card */}
          <Card className="lg:col-span-2">
            <CardHeader>
              <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                  <div
                    className="flex h-12 w-12 items-center justify-center rounded-full"
                    style={{ backgroundColor: goal.color || '#3b82f6' }}
                  >
                    <Target className="h-6 w-6 text-white" />
                  </div>
                  <div>
                    <CardTitle>{goal.name}</CardTitle>
                    {goal.description && (
                      <CardDescription>{goal.description}</CardDescription>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  {isCompleted && (
                    <Badge variant="default" className="bg-green-600">
                      <CheckCircle className="mr-1 h-3 w-3" />
                      Completed
                    </Badge>
                  )}
                  {isPaused && (
                    <Badge variant="secondary">
                      <Pause className="mr-1 h-3 w-3" />
                      Paused
                    </Badge>
                  )}
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Progress Section */}
              <div>
                <div className="flex justify-between text-sm mb-2">
                  <span className="text-muted-foreground">Progress</span>
                  <span className="font-medium">{progress.toFixed(1)}%</span>
                </div>
                <Progress value={progress} className="h-3" />
                <div className="flex justify-between mt-2">
                  <span className="text-2xl font-bold">
                    {formatMoney(goal.current_amount, goal.currency_code)}
                  </span>
                  <span className="text-muted-foreground">
                    of {formatMoney(goal.target_amount, goal.currency_code)}
                  </span>
                </div>
                {goal.remaining_amount > 0 && !isCompleted && (
                  <p className="text-sm text-muted-foreground mt-1">
                    {formatMoney(goal.remaining_amount, goal.currency_code)} remaining
                  </p>
                )}
              </div>

              {/* Target Date */}
              {goal.target_date && (
                <div className="flex items-center gap-2 text-sm">
                  <Calendar className="h-4 w-4 text-muted-foreground" />
                  <span>Target: {formatDate(goal.target_date)}</span>
                </div>
              )}

              {/* Actions */}
              {!isCompleted && (
                <div className="flex gap-3">
                  <Button onClick={() => setShowContributeDialog(true)}>
                    <Plus className="mr-2 h-4 w-4" />
                    Add Money
                  </Button>
                  {goal.current_amount > 0 && (
                    <Button variant="outline" onClick={() => setShowWithdrawDialog(true)}>
                      <Minus className="mr-2 h-4 w-4" />
                      Withdraw
                    </Button>
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Stats Card */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Statistics</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <p className="text-sm text-muted-foreground">Total Contributions</p>
                <p className="text-xl font-bold">{goal.contributions?.length || 0}</p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Created</p>
                <p className="font-medium">{formatDate(goal.created_at)}</p>
              </div>
              {goal.completed_at && (
                <div>
                  <p className="text-sm text-muted-foreground">Completed</p>
                  <p className="font-medium">{formatDate(goal.completed_at)}</p>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Contributions History */}
        <Card className="mt-6">
          <CardHeader>
            <CardTitle>Contribution History</CardTitle>
            <CardDescription>
              All deposits and withdrawals for this goal
            </CardDescription>
          </CardHeader>
          <CardContent>
            {goal.contributions && goal.contributions.length > 0 ? (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Date</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Notes</TableHead>
                    <TableHead className="w-[50px]"></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {goal.contributions.map((contribution) => (
                    <TableRow key={contribution.id}>
                      <TableCell>{formatDate(contribution.contribution_date)}</TableCell>
                      <TableCell
                        className={
                          contribution.amount >= 0 ? 'text-green-600' : 'text-red-600'
                        }
                      >
                        {contribution.amount >= 0 ? '+' : ''}
                        {formatMoney(contribution.amount, contribution.currency_code)}
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">
                          {contribution.type === 'linked' ? (
                            <>
                              <LinkIcon className="mr-1 h-3 w-3" />
                              Linked
                            </>
                          ) : (
                            'Manual'
                          )}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-muted-foreground">
                        {contribution.notes || '-'}
                      </TableCell>
                      <TableCell>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8 text-red-600"
                          onClick={() => {
                            setSelectedContribution(contribution)
                            setShowDeleteDialog(true)
                          }}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : (
              <div className="text-center py-8 text-muted-foreground">
                No contributions yet. Start saving towards your goal!
              </div>
            )}
          </CardContent>
        </Card>

        {/* Contribute Dialog */}
        <Dialog open={showContributeDialog} onOpenChange={setShowContributeDialog}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Add Money</DialogTitle>
              <DialogDescription>
                Add funds to "{goal.name}"
              </DialogDescription>
            </DialogHeader>
            <form onSubmit={handleContribute} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="contribute-amount">Amount ({goal.currency_code})</Label>
                <Input
                  id="contribute-amount"
                  type="number"
                  step="1"
                  min="1"
                  value={contributeForm.data.amount}
                  onChange={(e) => contributeForm.setData('amount', e.target.value)}
                  placeholder="0"
                  autoFocus
                />
                {contributeForm.errors.amount && (
                  <p className="text-sm text-red-600">{contributeForm.errors.amount}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="contribute-date">Date</Label>
                <Input
                  id="contribute-date"
                  type="date"
                  value={contributeForm.data.contribution_date}
                  onChange={(e) => contributeForm.setData('contribution_date', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="contribute-notes">Notes (Optional)</Label>
                <Textarea
                  id="contribute-notes"
                  value={contributeForm.data.notes}
                  onChange={(e) => contributeForm.setData('notes', e.target.value)}
                  placeholder="Add a note..."
                  rows={2}
                />
              </div>
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setShowContributeDialog(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={contributeForm.processing}>
                  {contributeForm.processing ? 'Adding...' : 'Add Money'}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* Withdraw Dialog */}
        <Dialog open={showWithdrawDialog} onOpenChange={setShowWithdrawDialog}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Withdraw Money</DialogTitle>
              <DialogDescription>
                Withdraw funds from "{goal.name}"
              </DialogDescription>
            </DialogHeader>
            <form onSubmit={handleWithdraw} className="space-y-4">
              <div className="p-3 rounded-lg bg-muted">
                <p className="text-sm text-muted-foreground">
                  Available balance:{' '}
                  <span className="font-medium text-foreground">
                    {formatMoney(goal.current_amount, goal.currency_code)}
                  </span>
                </p>
              </div>
              <div className="space-y-2">
                <Label htmlFor="withdraw-amount">Amount ({goal.currency_code})</Label>
                <Input
                  id="withdraw-amount"
                  type="number"
                  step="1"
                  min="1"
                  max={goal.current_amount}
                  value={withdrawForm.data.amount}
                  onChange={(e) => withdrawForm.setData('amount', e.target.value)}
                  placeholder="0"
                  autoFocus
                />
                {withdrawForm.errors.amount && (
                  <p className="text-sm text-red-600">{withdrawForm.errors.amount}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="withdraw-date">Date</Label>
                <Input
                  id="withdraw-date"
                  type="date"
                  value={withdrawForm.data.contribution_date}
                  onChange={(e) => withdrawForm.setData('contribution_date', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="withdraw-notes">Notes (Optional)</Label>
                <Textarea
                  id="withdraw-notes"
                  value={withdrawForm.data.notes}
                  onChange={(e) => withdrawForm.setData('notes', e.target.value)}
                  placeholder="Add a note..."
                  rows={2}
                />
              </div>
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setShowWithdrawDialog(false)}>
                  Cancel
                </Button>
                <Button type="submit" variant="destructive" disabled={withdrawForm.processing}>
                  {withdrawForm.processing ? 'Processing...' : 'Withdraw'}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* Delete Contribution Confirmation */}
        <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete Contribution</AlertDialogTitle>
              <AlertDialogDescription>
                Are you sure you want to delete this contribution? This will affect your goal's progress.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction
                onClick={handleDeleteContribution}
                className="bg-red-600 hover:bg-red-700"
              >
                Delete
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </AuthenticatedLayout>
  )
}
