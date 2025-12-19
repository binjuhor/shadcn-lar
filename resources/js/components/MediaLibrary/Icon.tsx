import React from 'react';

interface IconProps {
    icon: string;
    className?: string;
}

export default function Icon({ icon, className = '' }: IconProps) {
    return (
        <svg className={`media-library-icon ${className}`}>
            <use xlinkHref={`#icon-${icon}`}></use>
        </svg>
    );
}