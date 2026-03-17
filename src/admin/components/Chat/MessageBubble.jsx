import { Cpu } from 'lucide-react';

export default function MessageBubble({ message }) {
    const isAI = message.role === 'assistant';

    return (
        <div className={ `wpaim-bubble wpaim-bubble--${ isAI ? 'ai' : 'user' }` }>
            <div className="wpaim-bubble__content">
                <p>{ message.content }</p>
            </div>
            { isAI && message.model && (
                <div className="wpaim-bubble__meta">
                    <Cpu size={ 10 } strokeWidth={ 1.5 } />
                    <span>{ message.model }</span>
                    { message.tokens && <span>{ message.tokens } tokens</span> }
                </div>
            ) }
        </div>
    );
}
