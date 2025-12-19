import React from 'react';
import Icon from './Icon';

interface IconButtonProps {
    icon: string;
    level?: 'info' | 'warning' | 'error' | 'success';
}

export default function IconButton({ icon, level = 'info' }: IconButtonProps) {
    return (
        <span className={`media-library-button media-library-button-${level}`}>
            <Icon icon={icon} />
        </span>
    );
}