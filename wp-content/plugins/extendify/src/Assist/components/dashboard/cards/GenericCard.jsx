import { ActionButton } from '@assist/components/dashboard/buttons/ActionButton';
import { DismissButton } from '@assist/components/dashboard/buttons/DismissButton';
import { useTasksStore } from '@assist/state/tasks';

export const GenericCard = ({ task }) => {
	const { isCompleted, dismissTask } = useTasksStore();

	return (
		<div className="h-full justify-center overflow-hidden bg-white/95 text-base">
			<div className="flex flex-col items-center justify-center px-8 py-8 text-center md:m-8 md:px-0 md:py-0">
				{task?.htmlBefore()}

				<div className="flex flex-col items-center justify-center text-center">
					{task?.title && (
						<h2 className="mb-2 text-2xl font-semibold leading-10 md:mt-0 lg:text-3xl">
							{task.title}
						</h2>
					)}
					{task?.description && (
						<p className="m-0 text-sm md:text-base">{task.description}</p>
					)}
					<div className="cta mt-6 flex flex-wrap items-center text-sm md:gap-3">
						<ActionButton task={task} />
						{!isCompleted(task.slug) ? (
							<DismissButton
								task={task}
								onClick={() => dismissTask(task.slug)}
							/>
						) : null}
					</div>
				</div>
			</div>
		</div>
	);
};
