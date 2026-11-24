import { cva, type VariantProps } from 'class-variance-authority'
import { Slot } from 'radix-ui'
import { forwardRef, type ButtonHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'
import { Spinner } from './spinner'

export const buttonVariants = cva(
  'relative inline-flex select-none items-center justify-center gap-1.5 whitespace-nowrap rounded-lg font-medium transition-[background-color,border-color,color,box-shadow,opacity] duration-150 outline-none focus-visible:ring-[3px] focus-visible:ring-[var(--ring)] disabled:pointer-events-none disabled:opacity-50 active:scale-[0.985] [&_svg]:size-4 [&_svg]:shrink-0',
  {
    variants: {
      variant: {
        primary: 'bg-primary text-primary-fg shadow-[inset_0_1px_0_rgb(255_255_255/0.14),0_1px_2px_rgb(0_0_0/0.12)] hover:bg-primary-hover',
        ink: 'bg-fg text-bg shadow-xs hover:bg-fg/85',
        accent: 'bg-accent text-white shadow-xs hover:brightness-105 dark:text-black',
        secondary: 'bg-surface-3 text-fg hover:bg-border',
        outline: 'border border-border-strong bg-surface text-fg shadow-xs hover:bg-surface-3/70',
        ghost: 'text-muted hover:bg-surface-3 hover:text-fg',
        danger: 'bg-danger text-white shadow-xs hover:brightness-110 dark:text-black',
        link: 'h-auto px-0 text-primary underline-offset-4 hover:underline',
      },
      size: {
        sm: 'h-7 px-2.5 text-[0.78rem] [&_svg]:size-3.5',
        md: 'h-8.5 px-3 text-[0.8125rem]',
        lg: 'h-10 px-4 text-sm',
        icon: 'size-8.5',
        'icon-sm': 'size-7 [&_svg]:size-3.5',
      },
    },
    defaultVariants: { variant: 'primary', size: 'md' },
  },
)

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement>, VariantProps<typeof buttonVariants> {
  asChild?: boolean
  loading?: boolean
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild, loading, disabled, children, ...props }, ref) => {
    const Comp = asChild ? Slot.Root : 'button'
    return (
      <Comp
        ref={ref}
        className={cn(buttonVariants({ variant, size }), className)}
        disabled={disabled || loading}
        aria-busy={loading || undefined}
        {...props}
      >
        {asChild ? children : (<>{loading && <Spinner className="size-4" />}{children}</>)}
      </Comp>
    )
  },
)
Button.displayName = 'Button'
