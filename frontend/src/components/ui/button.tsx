import { cva, type VariantProps } from 'class-variance-authority'
import { Slot } from 'radix-ui'
import { forwardRef, type ButtonHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'
import { Spinner } from './spinner'

export const buttonVariants = cva(
  'relative inline-flex select-none items-center justify-center gap-2 whitespace-nowrap rounded-lg font-semibold transition-all duration-150 outline-none focus-visible:ring-4 focus-visible:ring-[var(--ring)] disabled:pointer-events-none disabled:opacity-55 active:translate-y-px [&_svg]:size-4 [&_svg]:shrink-0',
  {
    variants: {
      variant: {
        primary:
          'bg-primary text-primary-fg shadow-[inset_0_1px_0_rgb(255_255_255/0.18),0_1px_2px_rgb(15_30_80/0.25)] hover:bg-primary-hover hover:shadow-glow',
        accent: 'bg-accent text-white shadow-xs hover:brightness-105',
        secondary: 'bg-surface-3 text-fg hover:bg-border',
        outline: 'border border-border-strong bg-surface text-fg shadow-xs hover:bg-surface-2 hover:border-subtle/60',
        ghost: 'text-fg-soft hover:bg-surface-3 hover:text-fg',
        danger: 'bg-danger text-white shadow-xs hover:brightness-110',
        link: 'h-auto px-0 text-primary underline-offset-4 hover:underline',
      },
      size: {
        sm: 'h-8 px-3 text-[0.8125rem]',
        md: 'h-10 px-4 text-sm',
        lg: 'h-11 px-5 text-[0.95rem]',
        icon: 'size-9',
        'icon-sm': 'size-8',
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
